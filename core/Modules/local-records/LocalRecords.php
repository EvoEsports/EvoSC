<?php

namespace esc\Modules\LocalRecords;

use esc\Classes\Config;
use esc\Classes\Database;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;

class LocalRecords
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
     * LocalRecords constructor.
     */
    public function __construct()
    {
        Hook::add('PlayerFinish', [self::class, 'playerFinish'], false);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMap', [self::class, 'endMap']);
        Hook::add('PlayerConnect', [self::class, 'showManialink']);

        ManiaLinkEvent::add('local.delete', [self::class, 'delete']);
    }

    public static function showManialink(Player $player)
    {
        $localsJson = self::$localsJson;

        Template::show($player, 'local-records.update', compact('localsJson'));
        Template::show($player, 'local-records.manialink');
    }

    public static function delete(Player $player, string $localRank)
    {
        $map = MapController::getCurrentMap();
        $map->locals()->where('Rank', $localRank)->delete();
        self::cacheLocals($map);
        self::sendUpdatedLocals();
        warningMessage($player, ' deleted ', secondary("$localRank. local record"), ".")->sendAdmin();
    }

    public static function sendUpdatedLocals()
    {
        $localsJson = self::$localsJson;
        Template::showAll('local-records.update', compact('localsJson'));
    }

    public static function endMap(Map $map)
    {
        // $map->locals()->orderBy('Score')->skip(500)->delete();
    }

    public static function beginMap(Map $map)
    {
        self::fixRanks($map);
        self::cacheLocals($map);
        self::sendUpdatedLocals();
    }

    private static function fixRanks(Map $map)
    {
        Database::getConnection()->statement('SET @rank=0');
        Database::getConnection()->statement('UPDATE `local-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = ' . $map->id . ' ORDER BY `Score`');
    }

    private static function cacheLocals(Map $map)
    {
        self::$records = $map->locals()->orderBy('Rank')->limit(config('locals.limit'))->get();

        self::$localsJson = self::$records->map(function (LocalRecord $local) {
            return [
                'r' => $local->Rank,
                'c' => $local->Checkpoints,
                's' => $local->Score,
                'n' => $local->player->NickName,
                'l' => $local->player->Login,
            ];
        })->toJson();

        self::$records = self::$records->keyBy('Player');
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();

        if (self::$records->has($player->id)) {
            $oldRecord = $map->locals()->wherePlayer($player->id)->first();
            $oldRank   = $oldRecord->Rank;

            if ($oldRecord->Score < $score) {
                return;
            }

            $chatMessage = chatMessage()
                ->setIcon('')
                ->setColor(config('colors.local'));

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ', $oldRecord)->sendAll();

                return;
            }

            $map->locals()->updateOrCreate(['Player' => $player->id], [
                'Score'       => $score,
                'Checkpoints' => $checkpoints,
                'Rank'        => -1,
            ]);

            self::fixRanks($map);

            $newRecord = $map->locals()->wherePlayer($player->id)->first();
            $newRank   = $newRecord->Rank;
            $diff      = $oldRecord->Score - $score;

            if ($oldRank == $newRank) {
                $chatMessage->setParts($player, ' secured his/her ', $newRecord, ' (' . $oldRank . '. -' . formatScore($diff) . ')');
            } else {
                $chatMessage->setParts($player, ' gained the ', $newRecord, ' (' . $oldRank . '. -' . formatScore($diff) . ')');
            }

            if ($newRank <= config('locals.echo-top')) {
                $chatMessage->sendAll();
            } else {
                $chatMessage->send($player);
            }

            self::cacheLocals($map);
            self::sendUpdatedLocals();
            Hook::fire('PlayerLocal', $player, $newRecord);
        } else {
            $nextBetterRecord = $map->locals()->where('Score', '<=', $score)->orderByDesc('Score')->first();
            $newRank          = $nextBetterRecord ? $nextBetterRecord->Rank + 1 : 1;

            if ($newRank > config('locals.limit')) {
                return;
            }

            $map->locals()->updateOrCreate(['Player' => $player->id], [
                'Score'       => $score,
                'Checkpoints' => $checkpoints,
                'Rank'        => $newRank,
            ]);

            self::fixRanks($map);

            $newRecord = $map->locals()->wherePlayer($player->id)->first();

            $chatMessage = chatMessage($player, ' gained the ', $newRecord)
                ->setIcon('')
                ->setColor(config('colors.local'));

            if ($newRank <= config('locals.echo-top')) {
                $chatMessage->sendAll();
            } else {
                $chatMessage->send($player);
            }

            self::cacheLocals($map);
            self::sendUpdatedLocals();
            Hook::fire('PlayerLocal', $player, $newRecord);
        }
    }
}