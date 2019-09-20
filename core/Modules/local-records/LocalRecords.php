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
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $records;

    /**
     * @var Collection
     */
    private static $playerIdRankMap;

    private static $showTop;
    private static $show;

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
        Hook::add('EndMap', [self::class, 'fixRanks']);

        AccessRight::createIfMissing('local_delete', 'Delete local-records.');

        ManiaLinkEvent::add('local.delete', [self::class, 'delete'], 'local_delete');
        ManiaLinkEvent::add('locals.show', [self::class, 'showLocalsTable']);

        self::$showTop = config('locals.showtop');
        self::$show = config('locals.rows');
    }

    public static function beginMap(Map $map)
    {
        self::fixRanks($map);

        self::$records = $map->locals()->orderBy('Score')->limit(config('locals.limit'))->get()->keyBy('Player');
        $localsMap = self::$records->pluck('Rank', 'Player');

        self::$playerIdRankMap = onlinePlayers()->mapWithKeys(function (Player $player) use ($localsMap) {
            return [$player->id => $localsMap->get($player->id)];
        })->filter();

        onlinePlayers()->each([self::class, 'sendLocalsChunk']);
    }

    public static function fixRanks(Map $map)
    {
        Database::getConnection()->statement('SET @rank=0');
        Database::getConnection()->statement('UPDATE `local-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = '.$map->id.' ORDER BY `Score`');
    }

    public static function sendLocalsChunk(Player $player)
    {
        $showTop = self::$showTop;
        $show = self::$show - $showTop;
        $baseRank = self::$playerIdRankMap->get($player->id);

        $sortedRecords = self::$records->sortBy('Score');
        $records = $sortedRecords->take($showTop);

        if (!$baseRank) {
            $records = $records->merge(MapController::getCurrentMap()->locals()->orderByDesc('Score')->take($show)->get()->sortBy('Score'));
        } else {
            if ($baseRank <= $showTop) {
                $records = $sortedRecords->take(self::$show);
            } else {
                $startRank = $baseRank - floor($show * 0.7);
                $endRank = $baseRank + floor($show * 0.3);
                $records = $records->merge($sortedRecords->slice($startRank, $endRank));
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

        Template::show($player, 'local-records.update', compact('records'));
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

        $playerId = $player->id;
        if (self::$records->has($playerId)) {
            if (self::$records->get($playerId)->Score <= $score) {
                return;
            }
        }

        $map = MapController::getCurrentMap();
        $newRank = self::getNextBetterRank($player, $map, $score);

        if ($newRank > config('locals.limit')) {
            return;
        }

        if (self::$records->has($playerId)) {
            $oldRecord = self::$records->get($playerId);
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

            self::$playerIdRankMap->put($playerId, $newRank);
            self::$records->put($playerId, $oldRecord);

            $diff = $oldScore - $score;

            if ($oldRank == $newRank) {
                $chatMessage->setParts($player, ' secured his/her ', $oldRecord,
                    ' ('.$oldRank.'. -'.formatScore($diff).')');
            } else {
                self::incrementRanksAboveScore($map, $score, $oldScore);
                $chatMessage->setParts($player, ' gained the ', $oldRecord, ' ('.$oldRank.'. -'.formatScore($diff).')');
            }

            if ($newRank <= config('locals.echo-top')) {
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

            self::$playerIdRankMap->put($playerId, $newRank);
            self::$records->put($playerId, $newRecord);
            self::incrementRanksAboveScore($map, $score);

            $chatMessage = chatMessage($player, ' gained the ', $newRecord)
                ->setIcon('')
                ->setColor(config('colors.local'));

            if ($newRank <= config('locals.echo-top')) {
                $chatMessage->sendAll();
            } else {
                $chatMessage->send($player);
            }
        }

        onlinePlayers()->each([self::class, 'sendLocalsChunk']);
        Hook::fire('PlayerLocal', $player, $newRecord);
    }

    //Called on local.delete
    public static function delete(Player $player, string $localRank)
    {
        $map = MapController::getCurrentMap();
        $map->locals()->where('Rank', $localRank)->delete();
        warningMessage($player, ' deleted ', secondary("$localRank. local record"), ".")->sendAdmin();
        onlinePlayers()->each([self::class, 'sendLocalsChunk']);
    }

    public static function showLocalsTable(Player $player)
    {
        $map = MapController::getCurrentMap();
        $records = $map->locals()->orderBy('Score')->get();

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
        if($oldScore > 0){
            self::$records->where('Score', '>', $score)->where('Score', '<=', $oldScore)->transform(function (LocalRecord $record) {
                $record->Rank++;
                return $record;
            });

            $map->locals()->where('Score', '>', $score)->where('Score', '<=', $oldScore)->increment('Rank');
        }else{
            self::$records->where('Score', '>', $score)->transform(function (LocalRecord $record) {
                $record->Rank++;
                return $record;
            });

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
        $nextBetterRecord = self::$records->where('Score', '<=', $score)->sortByDesc('Score')->first();

        if ($nextBetterRecord) {
            if ($nextBetterRecord->Player == $player->id) {
                return $nextBetterRecord->Rank;
            }

            return $nextBetterRecord->Rank + 1;
        }

        return 1;
    }
}