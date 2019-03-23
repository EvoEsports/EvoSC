<?php

namespace esc\Modules\LocalRecords;

use esc\Classes\Config;
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
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
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
        $map->locals()->orderBy('Score')->skip(500)->delete();
    }

    public static function beginMap(Map $map)
    {
        $map->locals()->orderBy('Score')->limit(config('locals.limit'))->get()->each(function (LocalRecord $record, $key) {
            $record->update(['Rank' => $key + 1]);
        });
        self::cacheLocals($map);
        self::sendUpdatedLocals();
    }

    private static function cacheLocals(Map $map)
    {
        self::$records = $map->locals()->orderBy('Score')->limit(config('locals.limit'))->get();

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
            $oldRecord = self::$records->get($player->id);
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

            $newRecord = $map->locals()->updateOrCreate(['Player' => $player->id], [
                'Score'       => $score,
                'Checkpoints' => $checkpoints,
                'Rank'        => -1,
            ]);

            $nextBetterRecord = $map->locals()->where('Score', '<', $score)->orderByDesc('Score')->first();
            $newRank          = $nextBetterRecord ? $nextBetterRecord->Rank + 1 : $oldRank;
            $diff             = $oldRecord->Score - $score;

            if ($oldRank == $newRank) {
                $chatMessage->setParts($player, ' secured his/her ', $oldRecord, ' (' . $oldRank . '. -' . formatScore($diff) . ')');
            } else {
                $chatMessage->setParts($player, ' gained the ', $newRecord, ' (' . $oldRank . '. -' . formatScore($diff) . ')');
                $map->locals()->where('Rank', '>=', $newRank)->where('Rank', '<', $oldRank)->increment('Rank');
                $newRecord->update(['Rank' => $newRank]);
            }

            if ($newRank <= config('locals.echo-top')) {
                $chatMessage->send($player);
            } else {
                $chatMessage->sendAll();
            }

            self::cacheLocals($map);
            self::sendUpdatedLocals();
            Hook::fire('PlayerLocal', $newRecord);
        } else {
            $nextBetterRecord = $map->locals()->where('Score', '<', $score)->orderByDesc('Score')->first();
            $newRank          = $nextBetterRecord ? $nextBetterRecord->Rank + 1 : 1;
            $map->locals()->where('Rank', '>=', $newRank)->increment('Rank');

            $newRecord = $map->locals()->updateOrCreate(['Player' => $player->id], [
                'Score'       => $score,
                'Checkpoints' => $checkpoints,
                'Rank'        => $newRank,
            ]);

            $chatMessage = chatMessage($player, ' gained the ', $newRecord)
                ->setIcon('')
                ->setColor(config('colors.local'));

            if ($newRank <= config('locals.echo-top')) {
                $chatMessage->send($player);
            } else {
                $chatMessage->sendAll();
            }

            self::cacheLocals($map);
            self::sendUpdatedLocals();
            Hook::fire('PlayerLocal', $newRecord);
        }
    }
}