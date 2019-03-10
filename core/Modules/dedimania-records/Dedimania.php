<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
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
        self::$enabled = true;
        Log::logAddLine('Dedimania', 'Started. Session last updated: ' . self::getSessionLastUpdated());

        //Add hooks
        Hook::add('PlayerConnect', [DedimaniaApi::class, 'playerConnect']);
        Hook::add('PlayerConnect', [self::class, 'showManialink']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMatch', [self::class, 'endMatch']);

        //Check if session is still valid each 5 seconds
        Timer::create('dedimania.check_session', [self::class, 'checkSessionStillValid'], '5m');
        Timer::create('dedimania.report_players', [self::class, 'reportConnectedPlayers'], '5m');

        // KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::showManialink($player);
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
        onlinePlayers()->each([self::class, 'showManialink']);
    }

    public static function endMatch()
    {
        $map = MapController::getCurrentMap();
        self::setChallengeTimes($map);
        $map->dedis()->update(['New' => 0]);
    }

    public static function showManialink(Player $player)
    {
        $map = MapController::getCurrentMap();

        if (!$map) {
            return;
        }

        $dedisJson = self::getDedisJson($map);

        Template::show($player, 'dedimania-records.update', compact('dedisJson'));
        Template::show($player, 'dedimania-records.manialink');
    }

    public static function sendUpdatedDedis(Map $map)
    {
        $dedisJson = self::getDedisJson($map);

        Template::showAll('dedimania-records.update', compact('dedisJson'));
    }

    private static function getDedisJson(Map $map)
    {
        $dedis     = $map->dedis()->orderBy('Rank')->get();
        $playerIds = $dedis->pluck('Player');
        $players   = Player::whereIn('id', $playerIds)->get();

        return $dedis->map(function (Dedi $dedi) use ($players) {
            $player      = $players->where('id', $dedi->Player)->first();
            $checkpoints = collect(explode(',', $dedi->Checkpoints));
            $checkpoints = $checkpoints->map(function ($time) {
                return intval($time);
            });

            return [
                'rank'  => $dedi->Rank,
                'cps'   => $checkpoints,
                'score' => $dedi->Score,
                'name'  => $player->NickName,
                'login' => $player->Login,
            ];
        })->toJson();
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

        if ($newRank == 1) {
            //Ghost replay is needed for 1. dedi
            self::saveGhostReplay($dedi);
        }

        Log::logAddLine('Dedimania', $player . ' finished with time ' . formatScore($score));

        if ($dedi) {
            //Player has dedi on map

            if ($score == $dedi->Score) {
                chatMessage($player, ' equaled his/her ', secondary($dedi))->setIcon('')->setColor(config('colors.dedi'))->sendAll();
                Log::logAddLine('Dedimania', $player . ' equaled his/her record.', isVerbose());

                return;
            }

            $oldRank = $dedi->Rank;

            if ($score < $dedi->Score) {
                Log::logAddLine('Dedimania', $player . ' improved his/her record.', isVerbose());

                //Player improved his record
                if (($newRank <= self::$maxRank) || (isset($player->MaxRank) && $newRank <= $player->MaxRank)) {
                    $map->dedis()->where('Rank', '>=', $newRank)->increment('Rank');
                    $diff = $dedi->Score - $score;
                    $dedi->update(['Score' => $score, 'Checkpoints' => $checkpoints, 'New' => 1, 'Rank' => $newRank]);

                    if ($oldRank == $newRank) {
                        chatMessage($player, ' secured his/her ', $dedi, ' (' . $oldRank . '. -' . formatScore($diff) . ')')->setIcon('')->setColor(config('colors.dedi'))->sendAll();
                    } else {
                        chatMessage($player, ' gained the ', $dedi, ' (' . $oldRank . '. -' . formatScore($diff) . ')')->setIcon('')->setColor(config('colors.dedi'))->sendAll();
                    }

                    self::sendUpdatedDedis($map);
                } else {
                    Log::logAddLine('Dedimania', sprintf('%s does not get dedi %d, because player has no premium and server max rank is too low.', $player, $newRank), $player . ' finished with time ' . formatScore($score), isVerbose());
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
                'New'         => 1,
            ]);

            $dedi = $map->dedis()->wherePlayer($player->id)->first();

            if (($newRank <= self::$maxRank) || (isset($player->MaxRank) && $newRank <= $player->MaxRank)) {
                self::sendUpdatedDedis($map);
                chatMessage($player, ' gained the ', $dedi)->setIcon('')->setColor(config('colors.dedi'))->sendAll();
            } else {
                $dedi->update(['New' => 0]);
            }
        }
    }

    private static function getRankForScore(Map $map, int $score): int
    {
        $betterOrEarlierEqualRecords = $map->dedis()->where('Score', '<=', $score)->orderByDesc('Score')->get()->first();

        if (!$betterOrEarlierEqualRecords) {
            return 1;
        }

        return $betterOrEarlierEqualRecords->Rank + 1;
    }

    private static function saveGhostReplay(Dedi $dedi)
    {
        $oldGhostReplay = $dedi->ghost_replay;

        if ($oldGhostReplay && File::exists($oldGhostReplay)) {
            unlink($oldGhostReplay);
        }

        $ghostFile = sprintf('%s_%s_%d', stripAll($dedi->player->Login), stripAll($dedi->map->Name), $dedi->Score);

        try {
            $saved = Server::saveBestGhostsReplay($dedi->player->Login, 'Ghosts/' . $ghostFile);

            if ($saved) {
                $dedi->update(['ghost_replay' => $ghostFile]);
            }
        } catch (\Exception $e) {
            Log::error('Could not save ghost: ' . $e->getMessage());
        }
    }
}