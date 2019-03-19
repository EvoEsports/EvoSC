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
     * LocalRecords constructor.
     */
    public function __construct()
    {
        Hook::add('PlayerFinish', [LocalRecords::class, 'playerFinish']);
        Hook::add('BeginMatch', [LocalRecords::class, 'beginMatch']);
        Hook::add('PlayerConnect', [LocalRecords::class, 'showManialink']);

        ManiaLinkEvent::add('local.delete', [self::class, 'delete']);
    }

    public static function showManialink(Player $player)
    {
        $map = MapController::getCurrentMap();

        if (!$map) {
            return;
        }

        $localsJson = self::getLocalsJson($map);

        Template::show($player, 'local-records.update', compact('localsJson'));
        Template::show($player, 'local-records.manialink');
    }

    public static function delete(Player $player, string $localRank)
    {
        $map = MapController::getCurrentMap();
        $map->locals()->where('Rank', $localRank)->delete();
        self::sendUpdatedLocals($map);
        warningMessage($player, 'Deleted ', secondary("$localRank. local record"), ".")->sendAdmin();
    }

    public static function sendUpdatedLocals(Map $map)
    {
        $localsJson = self::getLocalsJson($map);
        Template::showAll('local-records.update', compact('localsJson'));
    }

    private static function getLocalsJson(Map $map)
    {
        $locals    = $map->locals()->orderBy('Rank')->limit(config('locals.limit'))->get();
        $playerIds = $locals->pluck('Player');
        $players   = Player::whereIn('id', $playerIds)->get();

        return $locals->map(function (LocalRecord $local) use ($players) {
            $player      = $players->where('id', $local->Player)->first();
            $checkpoints = collect(explode(',', $local->Checkpoints));
            $checkpoints = $checkpoints->map(function ($time) {
                return intval($time);
            });

            return [
                'rank'  => $local->Rank,
                'cps'   => $checkpoints,
                'score' => $local->Score,
                'name'  => $player->NickName,
                'login' => $player->Login,
            ];
        })->toJson();
    }

    public static function beginMatch()
    {
        $map = MapController::getCurrentMap();
        self::sendUpdatedLocals($map);
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();

        $chatMessage = chatMessage()
            ->setIcon('')
            ->setColor(config('colors.local'));

        $local      = $map->locals()->wherePlayer($player->id)->first();
        $localCount = $map->locals()->count();

        if ($localCount > 0) {
            $betterRank = $map->locals()->where('Score', '<=', $score)->orderByDesc('Score')->first();

            if ($betterRank) {
                $rank = $betterRank->Rank + 1;
            } else {
                $rank = $localCount + 1;
            }
        } else {
            $rank = 1;
        }

        if ($rank > config('locals.limit')) {
            return;
        }

        $halfLimit = config('locals.limit') / 2;

        if ($local != null) {
            if ($score == $local->Score) {
                $chatMessage->setParts($player, ' equaled his/her ', $local);

                return;
            }

            $oldRank = $local->Rank;

            if ($score < $local->Score) {
                $diff = $local->Score - $score;

                $local->update(['Score' => $score, 'Checkpoints' => $checkpoints, 'Rank' => $rank]);
                Hook::fire('PlayerLocal', $player, $local);
                self::sendUpdatedLocals($map);

                if ($oldRank == $local->Rank) {
                    $chatMessage->setParts($player, ' secured his/her ', $local, ' (' . $oldRank . '. -' . formatScore($diff) . ')');
                } else {
                    $chatMessage->setParts($player, ' gained the ', $local, ' (' . $oldRank . '. -' . formatScore($diff) . ')');
                }

                if ($rank > $halfLimit) {
                    $chatMessage->send($player);
                }else{
                    $chatMessage->sendAll();
                }
            }
        } else {
            $map->locals()->create([
                'Player'      => $player->id,
                'Map'         => $map->id,
                'Score'       => $score,
                'Checkpoints' => $checkpoints,
                'Rank'        => $rank,
            ]);
            Hook::fire('PlayerLocal', $player, $local);
            self::sendUpdatedLocals($map);

            $chatMessage->setParts($player, ' claimed the ', $local);

            if ($rank > $halfLimit) {
                $chatMessage->send($player);
            }else{
                $chatMessage->sendAll();
            }
        }
    }
}