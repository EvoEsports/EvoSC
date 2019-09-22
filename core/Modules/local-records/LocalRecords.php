<?php

namespace esc\Modules\LocalRecords;

use esc\Classes\Database;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;
use esc\Modules\RecordsTable;
use Illuminate\Support\Collection;

class LocalRecords implements ModuleInterface
{
    private static $showTop;
    private static $show;
    private static $limit;
    private static $echoTop;

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);

        AccessRight::createIfMissing('local_delete', 'Delete local-records.');

        ManiaLinkEvent::add('local.delete', [self::class, 'delete'], 'local_delete');
        ManiaLinkEvent::add('locals.show', [self::class, 'showLocalsTable']);

        self::$showTop = config('locals.showtop');
        self::$show = config('locals.rows');
        self::$limit = config('locals.limit');
        self::$echoTop = config('locals.echo-top');
    }

    public static function beginMap(Map $map)
    {
        self::sendLocalsChunk();
    }

    public static function sendLocalsChunk(Player $player = null)
    {
        if (!$player) {
            $players = onlinePlayers();
        } else {
            $players = [$player];
        }

        $map = MapController::getCurrentMap();
        $localsCount = $map->locals()->count();

        if ($localsCount > self::$limit) {
            $localsCount = self::$limit;
        }

        $showTop = self::$showTop;
        $show = self::$show - $showTop;

        foreach ($players as $player) {
            $baseRecord = $map->locals()->wherePlayer($player->id)->first();
            $baseRank = !empty($baseRecord) ? $baseRecord->Rank : null;

            if (!$baseRank || $baseRank >= $localsCount - $show) {
                $records = $map->locals()
                    ->where('Rank', '<=', $showTop)
                    ->orderBy('Rank')
                    ->get()
                    ->merge(
                        $records = $map->locals()
                            ->where('Rank', '>', $localsCount - $show)
                            ->orderBy('Rank')
                            ->limit($show)
                            ->get()
                    )->sortBy('Rank');
            } else {
                if ($baseRank <= self::$show) {
                    $records = $map->locals()
                        ->where('Rank', '<=', self::$show)
                        ->orderBy('Rank')
                        ->get();
                } else {
                    $records = $map->locals()
                        ->where('Rank', '<=', $showTop)
                        ->orderBy('Rank')
                        ->get()
                        ->merge(
                            $records = $map->locals()
                                ->where('Rank', '>', $baseRank - $show / 2)
                                ->where('Rank', '<', $baseRank + $show / 2)
                                ->orderBy('Rank')
                                ->get()
                        )->sortBy('Rank');
                }
            }

            $records = $records->values()->map(function (LocalRecord $record) {
                return [
                    'name' => $record->player->NickName,
                    'rank' => $record->Rank,
                    'score' => $record->Score,
                    'online' => onlinePlayers()->contains('id', $record->Player)
                ];
            });

            Template::show($player, 'local-records.update', compact('records'), true);
        }

        Template::executeMulticall();
    }

    public static function playerConnect(Player $player)
    {
        self::sendLocalsChunk($player);
        self::showManialink($player);
    }

    public static function showManialink(Player $player)
    {
        Template::show($player, 'local-records.manialink');
    }

    //Called on PlayerFinish
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();
        $playerId = $player->id;
        if ($map->locals()->wherePlayer($playerId)->where('Score', '<=', $score)->exists()) {
            return;
        }

        $newRank = self::getNextBetterRank($player, $map, $score);

        if ($newRank > self::$limit) {
            return;
        }

        if ($map->locals()->wherePlayer($playerId)->exists()) {
            $oldRecord = $map->locals()->wherePlayer($playerId)->get()->first();
            $oldScore = $oldRecord->Score;
            $oldRank = $oldRecord->Rank;

            $chatMessage = chatMessage()
                ->setIcon('')
                ->setColor(config('colors.local'));

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ', $oldRecord)->sendAll();

                return;
            }

            $oldRecord->Score = $score;
            $oldRecord->Checkpoints = $checkpoints;
            $oldRecord->Rank = $newRank;
            $oldRecord->save();

            $diff = $oldScore - $score;

            if ($oldRank == $newRank) {
                $chatMessage->setParts($player, ' secured his/her ', $oldRecord,
                    ' ('.$oldRank.'. -'.formatScore($diff).')');
            } else {
                self::incrementRanksAboveScore($map, $score, $oldScore);
                $chatMessage->setParts($player, ' gained the ', $oldRecord, ' ('.$oldRank.'. -'.formatScore($diff).')');
            }

            if ($newRank <= self::$echoTop) {
                $chatMessage->sendAll();
            } else {
                $chatMessage->send($player);
            }

            $newRecord = $oldRecord;
        } else {
            $newRecord = new LocalRecord();
            $newRecord->Map = $map->id;
            $newRecord->Player = $playerId;
            $newRecord->Checkpoints = $checkpoints;
            $newRecord->Score = $score;
            $newRecord->Rank = $newRank;
            $newRecord->save();

            self::incrementRanksAboveScore($map, $score);

            $chatMessage = chatMessage($player, ' gained the ', $newRecord)
                ->setIcon('')
                ->setColor(config('colors.local'));

            if ($newRank <= self::$echoTop) {
                $chatMessage->sendAll();
            } else {
                $chatMessage->send($player);
            }
        }

        self::sendLocalsChunk();
        Hook::fire('PlayerLocal', $player, $newRecord);
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
     * @param  Player  $player
     */
    public static function showLocalsTable(Player $player)
    {
        $map = MapController::getCurrentMap();
        $records = $map->locals()->orderBy('Rank')->get();

        RecordsTable::show($player, $map, $records, 'Local Records');
    }

    /**
     * Increment ranks of records with worse score
     *
     * @param  Map  $map
     * @param  int  $score
     * @param  int  $oldScore
     */
    private static function incrementRanksAboveScore(Map $map, int $score, int $oldScore = 0)
    {
        if ($oldScore > 0) {
            $map->locals()->where('Score', '>', $score)->where('Score', '<=', $oldScore)->increment('Rank');
        } else {
            $map->locals()->where('Score', '>', $score)->increment('Rank');
        }
    }

    /**
     * Get the rank for given score
     *
     * @param  Player  $player
     * @param  Map  $map
     * @param  int  $score
     * @return int|mixed
     */
    private static function getNextBetterRank(Player $player, Map $map, int $score)
    {
        $nextBetterRecord = $map->locals()
            ->where('Score', '<=', $score)
            ->where('Rank', '<=', self::$limit)
            ->orderByDesc('Rank')
            ->first();

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
        Database::getConnection()->statement('SET @rank=0');
        Database::getConnection()->statement('UPDATE `local-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = '.$map->id.' ORDER BY `Score`');
    }
}