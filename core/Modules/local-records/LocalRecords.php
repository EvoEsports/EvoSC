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
     * @var string
     */
    private static $localsJson;

    /**
     * @var Collection
     */
    private static $playerIdScoreMap;

    /**
     * LocalRecords constructor.
     */
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showManialink']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'initialize']);
        Hook::add('EndMap', [self::class, 'fixRanks']);

        AccessRight::createIfMissing('local_delete', 'Delete local-records.');

        ManiaLinkEvent::add('local.delete', [self::class, 'delete'], 'local_delete');
        ManiaLinkEvent::add('locals.show', [self::class, 'showLocalsTable']);
    }

    //Called on PlayerConnect
    public static function showManialink(Player $player)
    {
        $localsJson = self::$localsJson;

        Template::show($player, 'local-records.update', compact('localsJson'));
        Template::show($player, 'local-records.manialink');
    }

    public static function showLocalsTable(Player $player)
    {
        $map = MapController::getCurrentMap();
        $records = $map->locals()->orderBy('Score')->get();

        RecordsTable::show($player, $map, $records, 'Local Records');
    }

    //Called on PlayerFinish
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $playerId = $player->id;
        if (self::$playerIdScoreMap->has($playerId)) {
            if (self::$playerIdScoreMap->get($playerId) <= $score) {
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

            self::$playerIdScoreMap->put($playerId, $score);
            self::$records->put($playerId, $oldRecord);

            $diff = $oldScore - $score;

            if ($oldRank == $newRank) {
                $chatMessage->setParts($player, ' secured his/her ', $oldRecord,
                    ' ('.$oldRank.'. -'.formatScore($diff).')');
            } else {
                self::incrementRanksAboveScore($map, $score);
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

            self::$playerIdScoreMap->put($playerId, $score);
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

        self::cacheAndSendLocals();
        Hook::fire('PlayerLocal', $player, $newRecord);
    }

    //Called on local.delete
    public static function delete(Player $player, string $localRank)
    {
        $map = MapController::getCurrentMap();
        $map->locals()->where('Rank', $localRank)->delete();
        warningMessage($player, ' deleted ', secondary("$localRank. local record"), ".")->sendAdmin();
        self::initialize($map);
    }

    public static function initialize(Map $map)
    {
        self::$playerIdScoreMap = collect();
        self::$localsJson = "[]";
        self::fixRanks($map);
        self::$records = $map->locals()->orderBy('Score')->limit(config('locals.limit'))->get()->keyBy('Player');
        self::cacheAndSendLocals();
    }

    /**
     * Assign ranks to records of a certain map
     *
     * @param  Map  $map
     */
    public static function fixRanks(Map $map)
    {
        Database::getConnection()->statement('SET @rank=0');
        Database::getConnection()->statement('UPDATE `local-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = '.$map->id.' ORDER BY `Score`');
    }

    /**
     * Cache the locals and send them to everyone
     */
    private static function cacheAndSendLocals()
    {
        $localsJson = self::createJsonFromLocals(self::$records);
        self::$playerIdScoreMap = self::$records->pluck('Score', 'Player');
        self::$localsJson = $localsJson;
        Template::showAll('local-records.update', compact('localsJson'));
    }

    /**
     * Get the JSON send to to the player from a collection of locals
     *
     * @param  Collection  $locals
     * @return string
     */
    private static function createJsonFromLocals(Collection $locals)
    {
        $playerIds = $locals->pluck('Player');
        $players = Player::whereIn('id', $playerIds)->get()->keyBy('id');

        return $locals->sortBy('Rank')->transform(function (LocalRecord $local) use ($players) {
            return [
                'r' => $local->Rank,
                's' => $local->Score,
                'n' => $players->get($local->Player)->NickName,
                'l' => $players->get($local->Player)->Login
            ];
        })->values()->toJson();
    }

    /**
     * Increment ranks of records with worse score
     *
     * @param  Map  $map
     * @param  int  $score
     */
    private static function incrementRanksAboveScore(Map $map, int $score)
    {
        self::$records->where('Score', '>', $score)->transform(function (LocalRecord $record) {
            $record->Rank++;
            return $record;
        });

        $map->locals()->where('Score', '>', $score)->increment('Rank');
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

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function start(string $mode)
    {
        // TODO: Implement start() method.
    }
}