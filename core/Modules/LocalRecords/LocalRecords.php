<?php

namespace EvoSC\Modules\LocalRecords;

use EvoSC\Classes\AwaitAction;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Classes\Utility;
use EvoSC\Controllers\MapController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\RecordsTable\RecordsTable;

class LocalRecords extends Module implements ModuleInterface
{
    const TABLE = 'local-records';

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerPb', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);

        AccessRight::add('local_delete', 'Delete local-records.');

        ManiaLinkEvent::add('local.delete', [self::class, 'delete'], 'local_delete');
        ManiaLinkEvent::add('locals.show', [self::class, 'showLocalsTable']);
    }

    /**
     * @param Map $map
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function beginMap(Map $map)
    {
        Utility::fixRanks('local-records', $map->id, config('locals.limit', 200));
        self::sendLocalsChunk();
    }

    /**
     * @param Player|null $playerIn
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendLocalsChunk(Player $playerIn = null)
    {
        Utility::sendRecordsChunk(self::TABLE, 'locals', 'LocalRecords.update', $playerIn);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'LocalRecords.manialink');
        self::sendLocalsChunk($player);
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();
        $newRank = Utility::getNextBetterRank(self::TABLE, $map->id, $score);

        if ($newRank > ($localsLimit = config('locals.limit', 200))) {
            return;
        }

        if ($localsLimit == 0) {
            throw new \RuntimeException("Locals limit is zero!");
            return;
        }

        $chatMessage = chatMessage()
            ->setIcon('')
            ->setColor(config('locals.text-color'));

        $playerHasLocal = DB::table(self::TABLE)
            ->where('Map', '=', $map->id)
            ->where('Player', '=', $player->id)
            ->exists();

        if ($playerHasLocal) {
            $oldRecord = DB::table(self::TABLE)
                ->where('Map', '=', $map->id)
                ->where('Player', '=', $player->id)
                ->first();

            $oldRank = $oldRecord->Rank;

            if ($oldRecord->Score < $score) {
                return;
            }

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled their ', secondary($oldRank . '.'), ' local record ', secondary(formatScore($score)));
                if ($newRank <= config('locals.echo-top', 100)) {
                    $chatMessage->sendAll();
                } else {
                    $chatMessage->send($player);
                }

                return;
            }

            $diff = $oldRecord->Score - $score;

            if ($oldRank == $newRank) {
                DB::table(self::TABLE)
                    ->updateOrInsert([
                        'Map' => $map->id,
                        'Player' => $player->id
                    ], [
                        'Score' => $score,
                        'Checkpoints' => $checkpoints,
                    ]);

                $chatMessage->setParts($player, ' secured their ', secondary($newRank . '.'), ' local record ', secondary(formatScore($score) . ' (' . $oldRank . '. -' . formatScore($diff) . ')'));

                if ($newRank <= config('locals.echo-top', 100)) {
                    $chatMessage->sendAll();
                } else {
                    $chatMessage->send($player);
                }
            } else {
                DB::table(self::TABLE)
                    ->where('Map', '=', $map->id)
                    ->whereBetween('Rank', [$newRank, $oldRank])
                    ->increment('Rank');

                DB::table(self::TABLE)
                    ->updateOrInsert([
                        'Map' => $map->id,
                        'Player' => $player->id
                    ], [
                        'Score' => $score,
                        'Checkpoints' => $checkpoints,
                        'Rank' => $newRank,
                    ]);

                $chatMessage->setParts($player, ' gained the ', secondary($newRank . '.'), ' local record ', secondary(formatScore($score) . ' (' . $oldRank . '. -' . formatScore($diff) . ')'));

                if ($newRank <= config('locals.echo-top', 100)) {
                    $chatMessage->sendAll();
                } else {
                    $chatMessage->send($player);
                }
            }

            self::sendLocalsChunk();
            DB::table(self::TABLE)->where('Map', '=', $map->id)->where('Rank', '>', $localsLimit)->delete();
        } else {
            DB::table(self::TABLE)
                ->where('Map', '=', $map->id)
                ->where('Rank', '>=', $newRank)
                ->increment('Rank');

            DB::table(self::TABLE)
                ->updateOrInsert([
                    'Map' => $map->id,
                    'Player' => $player->id
                ], [
                    'Score' => $score,
                    'Checkpoints' => $checkpoints,
                    'Rank' => $newRank,
                ]);

            $chatMessage = $chatMessage->setParts($player, ' gained the ', secondary($newRank . '.'), ' local record ', secondary(formatScore($score)));

            if ($newRank <= config('locals.echo-top', 100)) {
                $chatMessage->sendAll();
            } else {
                $chatMessage->send($player);
            }

            self::sendLocalsChunk();
            DB::table(self::TABLE)->where('Map', '=', $map->id)->where('Rank', '>', $localsLimit)->delete();
        }
    }

    /**
     * @param Player $player
     * @param string $localRank
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function delete(Player $player, string $localRank)
    {
        $map = MapController::getCurrentMap();
        AwaitAction::add($player, "Delete \$<" . secondary("$localRank. local record") . "\$>?", function () use ($localRank, $map, $player) {
            DB::table(self::TABLE)->where('Map', '=', $map->id)->where('Rank', $localRank)->delete();
            dangerMessage($player, ' deleted ', secondary("$localRank. local record"), ".")->sendAdmin();
            self::sendLocalsChunk();
        });
    }

    /**
     * @param Player $player
     */
    public static function showLocalsTable(Player $player)
    {
        $map = MapController::getCurrentMap();

        $records = DB::table(self::TABLE)
            ->select(['Rank', 'local-records.Score as Score', 'NickName', 'Login', 'Player', 'players.id as id'])
            ->where('Map', '=', $map->id)
            ->leftJoin('players', 'players.id', '=', 'local-records.Player')
            ->orderBy('Rank')
            ->get();

        RecordsTable::show($player, $map, $records, 'Local Records');
    }
}