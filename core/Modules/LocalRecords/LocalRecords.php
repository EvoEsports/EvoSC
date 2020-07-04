<?php

namespace EvoSC\Modules\LocalRecords;

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
use Illuminate\Support\Collection;

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

    public static function beginMap(Map $map)
    {
        Utility::fixRanks('local-records', $map->id, config('locals.limit', 200));
        self::sendLocalsChunk();
    }

    public static function sendLocalsChunk(Player $playerIn = null)
    {
        if (!$map = MapController::getCurrentMap()) {
            return;
        }

        if (!$playerIn) {
            $players = onlinePlayers();
        } else {
            $players = collect([$playerIn]);
        }

        $count = DB::table(self::TABLE)->where('Map', '=', $map->id)->count();
        $top = config('locals.show-top', 3);
        $fill = config('locals.rows', 16);

        if ($count <= $fill) {
            dump("default");
            $localsJson = DB::table(self::TABLE)
                ->selectRaw('Rank as rank, `' . self::TABLE . '`.Score as score, NickName as name, Login as login, "[]" as cps')
                ->leftJoin('players', 'players.id', '=', self::TABLE . '.Player')
                ->where('Map', '=', $map->id)
                ->where('Rank', '<=', $fill)
                ->orderBy('rank')
                ->get()
                ->toJson();

            Template::showAll('LocalRecords.update', compact('localsJson'));
            return;
        }

        $playerRanks = DB::table(self::TABLE)
            ->select(['Player', 'Rank'])
            ->where('Map', '=', $map->id)
            ->whereIn('Player', $players->pluck('id'))
            ->pluck('Rank', 'Player');

        $defaultRecordsJson = null;
        $defaultTopView = null;

        foreach ($players as $player) {
            $localsJson = null;

            if ($playerRanks->has($player->id)) {
                $baseRank = (int)$playerRanks->get($player->id);
            } else {
                if (!is_null($defaultRecordsJson)) {
                    Template::show($player, 'LocalRecords.update', ['localsJson' => $defaultRecordsJson], true, 20);
                    continue;
                }
                $baseRank = $count;
            }

            if ($baseRank <= $fill) {
                if (is_null($defaultTopView)) {
                    $defaultTopView = DB::table(self::TABLE)
                        ->selectRaw('Rank as rank, `' . self::TABLE . '`.Score as score, NickName as name, Login as login, "[]" as cps')
                        ->leftJoin('players', 'players.id', '=', self::TABLE . '.Player')
                        ->where('Map', '=', $map->id)
                        ->WhereBetween('Rank', [$count - $fill + $top, $count])
                        ->orWhere('Map', '=', $map->id)
                        ->where('Rank', '<=', $top)
                        ->orderBy('rank')
                        ->get()
                        ->toJson();
                }
                $localsJson = $defaultTopView;
            }

            if (!isset($localsJson)) {
                $range = Utility::getRankRange($baseRank, $top, $fill, $count);

                $localsJson = DB::table(self::TABLE)
                    ->selectRaw('Rank as rank, `' . self::TABLE . '`.Score as score, NickName as name, Login as login, "[]" as cps')
                    ->leftJoin('players', 'players.id', '=', self::TABLE . '.Player')
                    ->where('Map', '=', $map->id)
                    ->WhereBetween('Rank', $range)
                    ->orWhere('Map', '=', $map->id)
                    ->where('Rank', '<=', $top)
                    ->orderBy('rank')
                    ->get()
                    ->toJson();
            }

            if ($baseRank == $count) {
                $defaultRecordsJson = $localsJson;
            }

            Template::show($player, 'LocalRecords.update', compact('localsJson'), true, 20);
        }

        Template::executeMulticall();
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

            $chatMessage = chatMessage()
                ->setIcon('')
                ->setColor(config('locals.text-color'));

            if ($oldRecord->Score < $score) {
                return;
            }

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ',
                    secondary($newRank . '.$') . config('locals.text-color') . ' local record ' . secondary(formatScore($score)));
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

                $chatMessage->setParts($player, ' secured his/her ',
                    secondary($newRank . '.$') . config('locals.text-color') . ' local record ' . secondary(formatScore($score)),
                    ' (' . $oldRank . '. -' . formatScore($diff) . ')');

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

                $chatMessage->setParts($player, ' gained the ',
                    secondary($newRank . '.$') . config('locals.text-color') . ' local record ' . secondary(formatScore($score)),
                    ' (' . $oldRank . '. -' . formatScore($diff) . ')');

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

            $chatMessage = chatMessage($player, ' gained the ',
                secondary($newRank . '.$') . config('locals.text-color') . ' local record ' . secondary(formatScore($score)))
                ->setIcon('')
                ->setColor(config('locals.text-color'));

            if ($newRank <= config('locals.echo-top', 100)) {
                $chatMessage->sendAll();
            } else {
                $chatMessage->send($player);
            }

            self::sendLocalsChunk();
            DB::table(self::TABLE)->where('Map', '=', $map->id)->where('Rank', '>', $localsLimit)->delete();
        }
    }

    //Called on local.delete
    public static function delete(Player $player, string $localRank)
    {
        $map = MapController::getCurrentMap();
        DB::table(self::TABLE)->where('Map', '=', $map->id)->where('Rank', $localRank)->delete();
        warningMessage($player, ' deleted ', secondary("$localRank. local record"), ".")->sendAdmin();
        self::sendLocalsChunk();
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