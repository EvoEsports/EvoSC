<?php

namespace esc\Modules\LocalRecords;

use esc\Classes\Cache;
use esc\Classes\Database;
use esc\Classes\DB;
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
     * @var Collection
     */
    private static $records;

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$showTop = config('locals.showtop');
        self::$show = config('locals.rows');
        self::$limit = config('locals.limit');
        self::$echoTop = config('locals.echo-top');

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish'], false, Hook::PRIORITY_HIGH);
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

    public static function sendLocalsChunk(Player $player = null, bool $saveToCache = false)
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

        $onlinePlayers = onlinePlayers();
        $playerMap = Player::whereIn('id',
            DB::table('local-records')->where('Map', '=', $map->id)->pluck('Player'))->pluck('NickName', 'id');
        $showTop = self::$showTop;
        $show = self::$show - $showTop;

        $topIds = range(1, $showTop);

        foreach ($players as $player) {
            $baseRecord = self::$records->get($player->id);
            $baseRank = !empty($baseRecord) ? $baseRecord->Rank : null;

            if (!$baseRank || $baseRank > $localsCount - $show) {
                $bottomIds = range($localsCount - $show + 1, $localsCount);
            } else {
                if ($baseRank <= self::$show) {
                    $bottomIds = range($showTop + 1, self::$show);
                } else {
                    $bottomIds = range(ceil($baseRank - $show / 2) + 1, ceil($baseRank + $show / 2));
                }
            }

            $selectRanks = array_merge($topIds, $bottomIds);
            $records = DB::table('local-records')->where('Map', '=', $map->id)
                ->whereIn('Rank', $selectRanks)->orderBy('Rank')->get();

            $records->transform(function ($record) use ($onlinePlayers, $playerMap) {
                return [
                    'name' => $playerMap->get($record->Player),
                    'rank' => $record->Rank,
                    'score' => $record->Score,
                    'online' => $onlinePlayers->contains('id', $record->Player)
                ];
            });

            if($saveToCache){
                $xml = Template::toString('local-records.update', compact('records'));
                Cache::put('local_records.xml', $xml);
            }

            Template::show($player, 'local-records.update', compact('records'), true);
        }

        Template::executeMulticall();
    }

    public static function playerConnect(Player $player)
    {
        self::showManialink($player);
        self::sendLocalsChunk($player);
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
        if (self::$records->has($playerId)) {
            if (self::$records->get($playerId)->Score <= $score) {
                return;
            }
        }

        $oldRecord = self::$records->get($playerId);
        $newRank = self::getNextBetterRank($player, $score);

        if ($newRank > self::$limit) {
            return;
        }

        if ($oldRecord) {
            $chatMessage = chatMessage()
                ->setIcon('')
                ->setColor(config('colors.local'));

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ',
                    self::toString($oldRecord->Rank, $score))->sendAll();

                return;
            }

            DB::table('local-records')->where('id', '=', $oldRecord->id)->update([
                'Score' => $score,
                'Checkpoints' => $checkpoints,
                'Rank' => $newRank
            ]);

            $diff = $oldRecord->Score - $score;

            if ($oldRecord->Rank == $newRank) {
                $chatMessage->setParts($player, ' secured his/her ', self::toString($newRank, $score),
                    ' ('.$oldRecord->Rank.'. -'.formatScore($diff).')');
            } else {
                self::incrementRanksAboveScore($map, $score, $oldRecord->Score);
                $chatMessage->setParts($player, ' gained the ', self::toString($newRank, $score),
                    ' ('.$oldRecord->Rank.'. -'.formatScore($diff).')');
            }

            self::$records->where('id', $oldRecord->id)->transform(function ($record) use ($score, $newRank) {
                $record->Score = $score;
                $record->Rank = $newRank;

                return $record;
            });
        } else {
            $localId = DB::table('local-records')->insertGetId([
                'Map' => $map->id,
                'Player' => $playerId,
                'Score' => $score,
                'Checkpoints' => $checkpoints,
                'Rank' => $newRank
            ]);

            $record = DB::table('local-records')->where('id', '=', $localId)->select([
                'id', 'Player', 'Score', 'Rank'
            ])->first();
            self::$records->put($playerId, $record);

            self::incrementRanksAboveScore($map, $score);

            $chatMessage = chatMessage($player, ' gained the ', self::toString($newRank, $score))
                ->setIcon('')
                ->setColor(config('colors.local'));
        }

        if ($newRank <= self::$echoTop) {
            $chatMessage->sendAll();
        } else {
            $chatMessage->send($player);
        }

        self::sendLocalsChunk();
        Hook::fire('PlayerLocal', $player, $newRank, $score, $checkpoints);
    }

    public static function toString($rank, $score)
    {
        return secondary($rank.'.$').config('colors.local').' local record '.secondary(formatScore($score));
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

        var_dump(self::$records->pluck('Rank'));

        $records = self::$records->sortBy('Rank')->map(function ($record) {
            return new LocalRecord(get_object_vars($record));
        });

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
     * @param  Player  $player
     * @param  int  $score
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
        DB::raw('UPDATE `local-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = '.$map->id.' ORDER BY `Score`');
        DB::table('local-records')->where('Map', '=', $map->id)->where('Rank', '>', self::$limit)->delete();
    }
}