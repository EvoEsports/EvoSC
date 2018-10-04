<?php

namespace esc\Modules\LocalRecords;

use esc\Classes\Config;
use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\HookController;
use esc\Controllers\KeyController;
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
        Hook::add('BeginMap', [LocalRecords::class, 'beginMap']);
        Hook::add('PlayerConnect', [LocalRecords::class, 'showManialink']);

        // KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        Config::configReload();
        TemplateController::loadTemplates();
        self::showManialink($player);
    }

    public static function showManialink(Player $player)
    {
        if ($player) {
            $map = MapController::getCurrentMap();

            if (!$map) {
                return;
            }

            $allDedis = $map->locals->sortBy('Rank');

            //Get player dedi
            $playerRecord = $map->locals()->wherePlayer($player->id)->first();

            if (!$playerRecord) {
                //Player has no dedi, get player local
                $record = $map->locals()->wherePlayer($player->id)->first();

                if ($record) {
                    $localCps = explode(',', $record->Checkpoints);
                    array_walk($localCps, function (&$time) {
                        $time = intval($time);
                    });

                    $localRank = -1;
                } else {
                    //Player does not have a local
                    $localRank = -1;
                    $localCps  = [];
                }
            } else {
                $localRank = $playerRecord->Rank;
                $localCps  = explode(',', $playerRecord->Checkpoints);
                array_walk($localCps, function (&$time) {
                    $time = intval($time);
                });
            }

            $cpCount       = (int)$map->gbx->CheckpointsPerLaps;
            $onlinePlayers = onlinePlayers()->pluck('Login');

            $records = $allDedis->map(function (LocalRecord $dedi) {
                    $nick = str_replace('\\', "\\\\", str_replace('"', "''", $dedi->player->NickName));

                    return sprintf('%d => ["cps" => "%s", "score" => "%s", "score_raw" => "%s", "nick" => "%s", "login" => "%s"]',
                        $dedi->Rank, $dedi->Checkpoints, formatScore($dedi->Score), $dedi->Score, $nick, $dedi->player->Login);
                })->implode(",\n");

            Template::show($player, 'local-records.manialink2', compact('records', 'localRank', 'localCps', 'cpCount', 'onlinePlayers'));
        }
    }

    /**
     * Called @ PlayerFinish
     *
     * @param Player $player
     * @param int    $score
     */
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();

        $localCount = $map->locals()->count();

        $local = $map->locals()->wherePlayer($player->id)->first();
        if ($local != null) {
            if ($score == $local->Score) {
                ChatController::message(onlinePlayers(), '_local', 'Player ', $player, ' equaled his/her ', $local);

                return;
            }

            $oldRank = $local->Rank;

            if ($score < $local->Score) {
                $diff = $local->Score - $score;
                $local->update(['Score' => $score, 'Checkpoints' => $checkpoints]);
                $local = self::fixLocalRecordRanks($map, $player);

                if ($oldRank == $local->Rank) {
                    ChatController::message(onlinePlayers(), '_local', 'Player ', $player, ' secured his/her ', $local, ' (-' . formatScore($diff) . ')');
                } else {
                    ChatController::message(onlinePlayers(), '_local', 'Player ', $player, ' gained the ', $local, ' (-' . formatScore($diff) . ')');
                }
                Hook::fire('PlayerLocal', $player, $local);
                self::sendUpdateDediManialink($local, $oldRank);
            }
        } else {
            if ($localCount < 100) {
                $map->locals()->create([
                    'Player'      => $player->id,
                    'Map'         => $map->id,
                    'Score'       => $score,
                    'Checkpoints' => $checkpoints,
                    'Rank'        => 999,
                ]);
                $local = self::fixLocalRecordRanks($map, $player);
                ChatController::message(onlinePlayers(), '_local', 'Player ', $player, ' claimed the ', $local);
                Hook::fire('PlayerLocal', $player, $local);
                self::sendUpdateDediManialink($local);
            }
        }
    }

    public static function sendUpdateDediManialink(LocalRecord $record, $oldRank = null)
    {
        $nick         = str_replace('\\', "\\\\", str_replace('"', "''", $record->player->NickName));
        $updateRecord = sprintf('["rank" => "%d", "cps" => "%s", "score" => "%s", "score_raw" => "%s", "nick" => "%s", "login" => "%s", "oldRank" => "%s"]',
            $record->Rank, $record->Checkpoints, formatScore($record->Score), $record->Score, $nick,
            $record->player->Login, $oldRank ?: "-1");

        Template::showAll('local-records.update', compact('updateRecord'));
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

    /**
     * Called @ BeginMap
     */
    public static function beginMap()
    {
        onlinePlayers()->each([self::class, 'showManialink']);
    }
}