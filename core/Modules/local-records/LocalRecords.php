<?php

namespace esc\Modules;

use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Classes\Utlity;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\Player;

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

        AccessRight::createIfMissing('local_delete', 'Delete local-records.');

        ManiaLinkEvent::add('local.delete', [self::class, 'delete'], 'local_delete');
        ManiaLinkEvent::add('locals.show', [self::class, 'showLocalsTable']);

        Template::showAll('local-records.manialink');
    }

    public static function beginMap(Map $map)
    {
        self::fixRanks($map);
        self::sendLocalsChunk();
    }

    public static function sendLocalsChunk(Player $playerIn = null)
    {
        if (!$playerIn) {
            $players = onlinePlayers();
        } else {
            $players = [$playerIn];
        }

        $map = MapController::getCurrentMap();
        $count = DB::table(self::TABLE)->where('Map', '=', $map->id)->count();

        $top = config('locals.show-top', 3);
        $fill = config('locals.rows', 16);

        foreach ($players as $player) {
            $record = DB::table(self::TABLE)
                ->where('Map', '=', $map->id)
                ->where('Player', '=', $player->id)
                ->first();

            if ($record) {
                $baseRank = $record->Rank;
            } else {
                $baseRank = $count;
            }

            $range = Utlity::getRankRange($baseRank, $top, $fill, $count);

            $bottomRecords = DB::table(self::TABLE)
                ->where('Map', '=', $map->id)
                ->WhereBetween('Rank', $range)
                ->get();

            $topRecords = DB::table(self::TABLE)
                ->where('Map', '=', $map->id)
                ->where('Rank', '<=', $top)
                ->get();

            $records = $topRecords->merge($bottomRecords);

            $players = DB::table('players')
                ->whereIn('id', $records->pluck('Player'))
                ->get()
                ->keyBy('id');

            $records->transform(function ($local) use ($players) {
                $checkpoints = collect(explode(',', $local->Checkpoints));
                $checkpoints = $checkpoints->transform(function ($time) {
                    return intval($time);
                });

                $player = $players->get($local->Player);

                return [
                    'rank' => $local->Rank,
                    'cps' => $checkpoints,
                    'score' => $local->Score,
                    'name' => ml_escape($player->NickName),
                    'login' => $player->Login,
                ];
            });

            $localsJson = $records->sortBy('rank')->values()->toJson();

            Template::show($player, 'local-records.update', compact('localsJson'));
        }
    }

    public static function playerConnect(Player $player)
    {
        Template::show($player, 'local-records.manialink');
        self::sendLocalsChunk($player);
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();
        $nextBetterRecord = DB::table(self::TABLE)
            ->where('Map', '=', $map->id)
            ->where('Score', '<=', $score)
            ->orderByDesc('Score')
            ->first();

        $newRank = $nextBetterRecord ? $nextBetterRecord->Rank + 1 : 1;

        if ($newRank > config('locals.limit', 200)) {
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
                ->setColor(config('colors.local'));

            if ($oldRecord->Score < $score) {
                return;
            }

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ',
                    secondary($newRank . '.$') . config('colors.local') . ' local record ' . secondary(formatScore($score)))->sendAll();

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
                    secondary($newRank . '.$') . config('colors.local') . ' local record ' . secondary(formatScore($score)),
                    ' (' . $oldRank . '. -' . formatScore($diff) . ')')->sendAll();
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
                    secondary($newRank . '.$') . config('colors.local') . ' local record ' . secondary(formatScore($score)),
                    ' (' . $oldRank . '. -' . formatScore($diff) . ')')->sendAll();
            }

            self::sendLocalsChunk();
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

            if ($newRank <= config('locals.echo-top', 100)) {
                chatMessage($player, ' gained the ',
                    secondary($newRank . '.$') . config('colors.local') . ' local record ' . secondary(formatScore($score)))
                    ->setIcon('')
                    ->setColor(config('colors.local'))
                    ->sendAll();
            }

            self::sendLocalsChunk();
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

    public static function fixRanks(Map $map)
    {
        DB::raw('SET @rank=0');
        DB::raw('UPDATE `local-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = ' . $map->id . ' ORDER BY `Score`');
        DB::table(self::TABLE)->where('Map', '=', $map->id)->where('Rank', '>', config('locals.limit', 200))->delete();
    }
}