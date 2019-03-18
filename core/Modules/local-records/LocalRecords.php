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
        Hook::add('BeginMap', [LocalRecords::class, 'beginMatch']);
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
        self::fixLocalRecordRanks($map);
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

    public static function beginMap(Map $map)
    {
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

        $local = $map->locals()->wherePlayer($player->id)->first();
        if ($local != null) {
            if ($score == $local->Score) {
                $chatMessage->setParts($player, ' equaled his/her ', $local);

                return;
            }

            $oldRank = $local->Rank;

            if ($score < $local->Score) {
                $diff = $local->Score - $score;
                $local->update(['Score' => $score, 'Checkpoints' => $checkpoints]);
                $local = self::fixLocalRecordRanks($map, $player);

                if ($oldRank == $local->Rank) {
                    $chatMessage->setParts($player, ' secured his/her ', $local, ' (' . $oldRank . '. -' . formatScore($diff) . ')')->sendAll();
                } else {
                    $chatMessage->setParts($player, ' gained the ', $local, ' (' . $oldRank . '. -' . formatScore($diff) . ')')->sendAll();
                }
                Hook::fire('PlayerLocal', $player, $local);
                self::sendUpdatedLocals($map);
            }
        } else {
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

            if ($rank <= config('locals.limit')) {
                $map->locals()->create([
                    'Player'      => $player->id,
                    'Map'         => $map->id,
                    'Score'       => $score,
                    'Checkpoints' => $checkpoints,
                    'Rank'        => $rank,
                ]);
                $local = self::fixLocalRecordRanks($map, $player);
                $chatMessage->setParts($player, ' claimed the ', $local)->sendAll();
                Hook::fire('PlayerLocal', $player, $local);
                self::sendUpdatedLocals($map);
            }
        }
    }

    /**
     * Fix local ranks
     *
     * @param Map         $map
     * @param Player|null $player
     *
     * @return null
     */
    private static function fixLocalRecordRanks(Map $map, Player $player = null)
    {
        $locals = $map->locals()->orderBy('Score')->get();
        $i      = 1;
        foreach ($locals as $local) {
            $local->update(['Rank' => $i]);
            $i++;
        }

        if ($player) {
            return $map->locals()->wherePlayer($player->id)->first();
        }

        return null;
    }
}