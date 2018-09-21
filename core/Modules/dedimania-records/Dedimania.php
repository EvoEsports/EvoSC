<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Dedi;
use esc\Models\Map;
use esc\Models\Player;

class Dedimania extends DedimaniaApi
{
    public function __construct()
    {
        if (!config('dedimania.enabled')) {
            return;
        }

        //Check for session key
        if (!self::getSessionKey()) {
            //There is no existing session

            if (!DedimaniaApi::openSession()) {
                //Failed to start session

                return;
            }
        } else {
            //Session exists

            if (!self::checkSession()) {
                //session expired

                if (!DedimaniaApi::openSession()) {
                    //Failed to start session

                    return;
                }
            }
        }

        //Session exists and is not expired
        self::$enabled  = true;
        self::$newTimes = collect();
        Log::logAddLine('Dedimania', 'Started. Session last updated: ' . self::getSessionLastUpdated());

        //Add hooks
        Hook::add('PlayerConnect', [self::class, 'showManialink']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMatch', [self::class, 'endMatch']);

        //Check if session is still valid each 5 seconds
        Timer::create('dedimania.check_session', [self::class, 'checkSessionStillValid'], '5m');
        Timer::create('dedimania.report_players', [self::class, 'reportConnectedPlayers'], '5m');
    }

    public static function reportConnectedPlayers()
    {
        $map  = MapController::getCurrentMap();
        $data = self::updateServerPlayers($map);

        if ($data && !isset($data->params->param->value->boolean)) {
            Log::logAddLine('!] Dedimania [!', 'Failed to report connected players. Trying again in 5 minutes.');
        }

        Timer::create('dedimania.report_players', [self::class, 'reportConnectedPlayers'], '5m');
    }

    public static function checkSessionStillValid()
    {
        if (!self::checkSession()) {
            //session expired

            if (!DedimaniaApi::openSession()) {
                //Failed to start session
                self::$enabled = false;

                return;
            }
        }

        Timer::create('dedimania.check_session', [self::class, 'checkSessionStillValid'], '5m');
    }

    public static function beginMap(Map $map)
    {
        $records = self::getChallengeRecords($map);
        if ($records && $records->count() > 0) {
            //Wipe all dedis for current map
            $map->dedis()->delete();

            //Insert dedis
            foreach ($records as $record) {
                self::insertRecord($map, $record);
            }
        }

        Log::logAddLine('Dedimania', "Loaded records for map $map");

        //Send manialink to online players
        if (onlinePlayers()->count() > 0) {
            onlinePlayers()->each([self::class, 'showManialink']);
        }
    }

    public static function endMatch()
    {
        $map = MapController::getCurrentMap();
        self::setChallengeTimes($map);
        self::$newTimes = collect();
        $map->dedis()->update(['New' => 0]);
    }

    private static function insertRecord(Map $map, $record)
    {
        $player = Player::find($record->login);

        if (!(isset($record->login, $record->nickname, $record->max_rank, $record->checkpoints))) {
            Log::logAddLine('Dedimania', 'Invalid record received.');

            return;
        }

        if (!$player) {
            //Player does not exist in database
            Player::create([
                'Login'    => $record->login,
                'NickName' => $record->nickname,
                'MaxRank'  => $record->max_rank,
            ]);

            $player = Player::find($record->login);
        }

        if ($player->NickName == $record->login) {
            $player->update([
                'NickName' => $record->nickname,
            ]);
        }

        //Create the dedi
        Dedi::create([
            'Map'         => $map->id,
            'Player'      => $player->id,
            'Score'       => $record->score,
            'Rank'        => $record->rank,
            'Checkpoints' => $record->checkpoints,
        ]);
    }

    public static function showManialink(Player $player)
    {
        if ($player) {
            $map = MapController::getCurrentMap();

            if (!$map) {
                return;
            }

            $allDedis = $map->dedis->sortBy('Rank');

            //Get player dedi
            $playerRecord = $map->dedis()->wherePlayer($player->id)->first();

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

            $records = '[' . $allDedis->map(function (Dedi $dedi) {
                    $nick = str_replace('\\', "\\\\", str_replace('"', "''", $dedi->player->NickName));

                    return sprintf('%d => ["cps" => "%s", "score" => "%s", "score_raw" => "%s", "nick" => "%s", "login" => "%s"]',
                        $dedi->Rank, $dedi->Checkpoints, formatScore($dedi->Score), $dedi->Score, $nick, $dedi->player->Login);
                })->implode(",\n") . ']';

            Template::show($player, 'dedimania-records.manialink', compact('records', 'localRank', 'localCps', 'cpCount', 'onlinePlayers'));
        }
    }

    public static function sendUpdateDediManialink(Dedi $record, $oldRank = null)
    {
        $nick         = str_replace('\\', "\\\\", str_replace('"', "''", $record->player->NickName));
        $updateRecord = sprintf('["rank" => "%d", "cps" => "%s", "score" => "%s", "score_raw" => "%s", "nick" => "%s", "login" => "%s", "oldRank" => "%d"]',
            $record->Rank, $record->Checkpoints, formatScore($record->Score), $record->Score, $nick,
            $record->player->Login, $oldRank);

        Template::showAll('dedimania-records.update', compact('updateRecord', 'oldRank'));
    }

    /**
     * called on playerFinish
     *
     * @param \esc\Models\Player $player
     * @param int                $score
     * @param string             $checkpoints
     */
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 8000) {
            //ignore times under 8 seconds
            return;
        }

        $map     = MapController::getCurrentMap();
        $dedi    = $map->dedis()->wherePlayer($player->id)->first();
        $newRank = self::getRankForScore($map, $score);

        Log::logAddLine('Dedimania', $player . ' finished with time ' . formatScore($score));

        if ($dedi) {
            //Player has dedi on map

            if ($score == $dedi->Score) {
                ChatController::message(onlinePlayers(), '_dedi', 'Player ', $player, ' equaled his/her ', $dedi);

                return;
            }

            $oldRank = $dedi->Rank;

            if ($score < $dedi->Score) {
                //Player improved his record
                if ($newRank <= (isset($player->MaxRank) ? $player->MaxRank : self::$maxRank)) {
                    $map->dedis()->where('Rank', '>=', $newRank)->increment('Rank');
                    $dedi->update(['Score' => $score, 'Checkpoints' => $checkpoints, 'New' => 1, 'Rank' => $newRank]);
                    $diff = $dedi->Score - $score;

                    if ($oldRank == $newRank) {
                        ChatController::message(onlinePlayers(), '_dedi', 'Player ', $player, ' secured his/her ', $dedi, ' (-' . formatScore($diff) . ')');
                    } else {
                        ChatController::message(onlinePlayers(), '_dedi', 'Player ', $player, ' gained the ', $dedi, ' (-' . formatScore($diff) . ')');
                    }
                    self::addNewTime($dedi);
                    self::sendUpdateDediManialink($dedi, $oldRank);
                }
            }
        } else {
            //Player does not have a dedi on map
            $map->dedis()->where('Rank', '>=', $newRank)->increment('Rank');

            $map->dedis()->create([
                'Player'      => $player->id,
                'Map'         => $map->id,
                'Score'       => $score,
                'Rank'        => $newRank,
                'Checkpoints' => $checkpoints,
            ]);

            $dedi = $map->dedis()->wherePlayer($player->id)->first();

            if ($dedi->Rank <= (isset($player->MaxRank) ? $player->MaxRank : self::$maxRank)) {
                self::addNewTime($dedi);
                self::sendUpdateDediManialink($dedi);
                ChatController::message(onlinePlayers(), '_dedi', 'Player ', $player, ' gained the ', $dedi);
            }
        }
    }

    private static function getRankForScore(Map $map, int $score): int
    {
        $betterOrEarlierEqualRecords = $map->dedis()->where('Score', '<=', $score)->orderByDesc('Score')->get()->first();

        if (!$betterOrEarlierEqualRecords) {
            //There is no better record or there are no records on this map
            return $map->dedis()->count() + 1;
        }

        return $betterOrEarlierEqualRecords->Rank + 1;
    }

    private static function addNewTime(Dedi $dedi)
    {
        $existingDedi = self::$newTimes->where('Player', $dedi->Player);

        if ($existingDedi->isNotEmpty()) {
            if (isset($existingDedi->ghostReplayFile) && file_exists($existingDedi->ghostReplayFile)) {
                unlink($existingDedi->ghostReplayFile);
            }

            self::$newTimes = self::$newTimes->diff($existingDedi);
        }

        $ghostFile = sprintf('%s_%s_%d', stripAll($dedi->player->Login), stripAll($dedi->map->Name), $dedi->Score);

        try {
            $saved = Server::saveBestGhostsReplay($dedi->player->Login, 'Ghosts/' . $ghostFile);
        } catch (\Exception $e) {
            Log::error('Could not save ghost: ' . $e->getMessage());
        }

        if (isset($saved) && !$saved) {
            Log::error('Saving top 1 dedi failed');

            return;
        } else {
            $dedi->ghostReplayFile = $ghostFile;
        }

        self::$newTimes->push($dedi);
    }
}