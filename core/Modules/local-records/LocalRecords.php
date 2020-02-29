<?php

namespace esc\Modules;

use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\Utlity;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class LocalRecords implements ModuleInterface
{
    private static $showTop;
    private static $show;
    private static $limit;
    private static $echoTop;

    /**
     * @var Collection
     */
    private static $records;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$showTop = config('locals.showtop');
        self::$show = config('locals.rows');
        self::$limit = config('locals.limit');
        self::$echoTop = config('locals.echo-top');

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerPb', [self::class, 'playerFinish'], false, Hook::PRIORITY_HIGH);
        Hook::add('BeginMap', [self::class, 'beginMap']);

        AccessRight::createIfMissing('local_delete', 'Delete local-records.');

        ManiaLinkEvent::add('local.delete', [self::class, 'delete'], 'local_delete');
        ManiaLinkEvent::add('locals.show', [self::class, 'showLocalsTable']);

        Template::showAll('local-records.manialink');
    }

    public static function beginMap(Map $map)
    {
        self::fixRanks($map);

        self::$records = DB::table('local-records')
            ->where('Map', '=', $map->id)
            ->orderBy('Rank')
            ->select(['id', 'Player', 'Score', 'Rank'])
            ->limit(self::$limit)
            ->get()
            ->keyBy('Player');

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
        $count = DB::table('local-records')->where('Map', '=', $map->id)->count();

        $top = config('locals.show-top', 3);
        $fill = config('locals.rows', 16);

        foreach ($players as $player) {
            $record = DB::table('local-records')
                ->where('Map', '=', $map->id)
                ->where('Player', '=', $player->id)
                ->first();

            if ($record) {
                $baseRank = $record->Rank;
            } else {
                $baseRank = $count;
            }

            $range = Utlity::getRankRange($baseRank, $top, $fill, $count);

            $bottom = DB::table('local-records')
                ->where('Map', '=', $map->id)
                ->WhereBetween('Rank', $range)
                ->get();

            $top = DB::table('local-records')
                ->where('Map', '=', $map->id)
                ->where('Rank', '<=', $top)
                ->get();

            $records = $top->merge($bottom);

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

            $localsJson = $records->sortBy('rank')->toJson();

            Template::show($player, 'local-records.update', compact('localsJson'), true);
        }

        Template::executeMulticall();
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
        $nextBetterRecord = DB::table('local-records')
            ->where('Map', '=', $map->id)
            ->where('Score', '<=', $score)
            ->orderByDesc('Score')
            ->first();

        $newRank = $nextBetterRecord ? $nextBetterRecord->Rank + 1 : 1;

        $playerHasLocal = DB::table('local-records')
            ->where('Map', '=', $map->id)
            ->where('Player', '=', $player->id)
            ->exists();

        if ($playerHasLocal) {
            $oldRecord = DB::table('local-records')
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
                DB::table('local-records')
                    ->updateOrInsert([
                        'Map' => $map->id,
                        'Player' => $player->id
                    ], [
                        'Score' => $score,
                        'Checkpoints' => $checkpoints,
                        'New' => 1,
                    ]);

                $chatMessage->setParts($player, ' secured his/her ',
                    secondary($newRank . '.$') . config('colors.local') . ' local record ' . secondary(formatScore($score)),
                    ' (' . $oldRank . '. -' . formatScore($diff) . ')')->sendAll();
            } else {
                if ($newRank > self::$limit) {
                    return;
                }

                DB::table('local-records')
                    ->where('Map', '=', $map->id)
                    ->whereBetween('Rank', [$newRank, $oldRank])
                    ->increment('Rank');

                DB::table('local-records')
                    ->updateOrInsert([
                        'Map' => $map->id,
                        'Player' => $player->id
                    ], [
                        'Score' => $score,
                        'Checkpoints' => $checkpoints,
                        'Rank' => $newRank,
                        'New' => 1,
                    ]);

                $chatMessage->setParts($player, ' gained the ',
                    secondary($newRank . '.$') . config('colors.local') . ' local record ' . secondary(formatScore($score)),
                    ' (' . $oldRank . '. -' . formatScore($diff) . ')')->sendAll();
            }

            self::sendLocalsChunk();
        } else {
            DB::table('local-records')
                ->where('Map', '=', $map->id)
                ->where('Rank', '>=', $newRank)
                ->increment('Rank');

            DB::table('local-records')
                ->updateOrInsert([
                    'Map' => $map->id,
                    'Player' => $player->id
                ], [
                    'Score' => $score,
                    'Checkpoints' => $checkpoints,
                    'Rank' => $newRank,
                    'New' => 1,
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

    public static function toString($rank, $score)
    {
        return secondary($rank . '.$') . config('colors.local') . ' local record ' . secondary(formatScore($score));
    }

    //Called on local.delete
    public static function delete(Player $player, string $localRank)
    {
        $map = MapController::getCurrentMap();
        $map->locals()->where('Rank', $localRank)->delete();
        warningMessage($player, ' deleted ', secondary("$localRank. local record"), ".")->sendAdmin();
        self::sendLocalsChunk();
    }

    /**
     * @param Player $player
     */
    public static function showLocalsTable(Player $player)
    {
        $map = MapController::getCurrentMap();

        $records = self::$records->sortBy('Rank')->map(function ($record) {
            return new LocalRecord(get_object_vars($record));
        });

        $records = $map->locals()->orderBy('Rank')->get();

        RecordsTable::show($player, $map, $records, 'Local Records');
    }

    /**
     * Increment ranks of records with worse score
     *
     * @param Map $map
     * @param int $score
     * @param int $oldScore
     */
    private static function incrementRanksAboveScore(Map $map, int $score, int $oldScore = 0)
    {
        if ($oldScore > 0) {
            DB::table('local-records')->where('Map', '=', $map->id)->where('Score', '>', $score)->where('Score', '<=',
                $oldScore)->increment('Rank');

            self::$records->transform(function ($record) use ($score, $oldScore) {
                if ($record->Score <= $oldScore && $record->Score > $score) {
                    $record->Rank++;
                }

                return $record;
            });
        } else {
            DB::table('local-records')->where('Map', '=', $map->id)->where('Score', '>', $score)->increment('Rank');

            self::$records->transform(function ($record) use ($score) {
                if ($record->Score > $score) {
                    $record->Rank++;
                }

                return $record;
            });
        }
    }

    /**
     * Get the rank for given score
     *
     * @param Player $player
     * @param int $score
     * @return int|mixed
     */
    private static function getNextBetterRank(Player $player, int $score)
    {
        $nextBetterRecord = self::$records->where('Score', '<=', $score)->sortByDesc('Rank')->first();

        if ($nextBetterRecord) {
            if ($nextBetterRecord->Player == $player->id) {
                return $nextBetterRecord->Rank;
            }

            return $nextBetterRecord->Rank + 1;
        }

        return 1;
    }

    public static function fixRanks(Map $map)
    {
        DB::raw('SET @rank=0');
        DB::raw('UPDATE `local-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = ' . $map->id . ' ORDER BY `Score`');
        DB::table('local-records')->where('Map', '=', $map->id)->where('Rank', '>', self::$limit)->delete();
    }
}