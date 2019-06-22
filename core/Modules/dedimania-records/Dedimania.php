<?php

namespace esc\Modules;


use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\MapController;
use esc\Models\Dedi;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Database\Eloquent\Model;

class Dedimania extends DedimaniaApi
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $dedis;

    /**
     * @var string
     */
    private static $dedisJson;

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
        Hook::add('EndMap', [self::class, 'endMap']);

        Log::logAddLine('Dedimania_constructor', 'call');

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

    public static function endMatch()
    {
        $map = MapController::getCurrentMap();
        self::setChallengeTimes($map);
    }

    public static function endMap(Map $map)
    {
        // $map->dedis()->update(['New' => 0]);
    }

    public static function showManialink(Player $player)
    {
        $dedisJson = self::$dedisJson;

        Template::show($player, 'dedimania-records.update', compact('dedisJson'));
        Template::show($player, 'dedimania-records.manialink');
    }

    public static function sendUpdatedDedis()
    {
        $dedisJson = self::$dedisJson;
        Template::showAll('dedimania-records.update', compact('dedisJson'));
    }

    private static function cacheDedis(Map $map)
    {
        self::$dedis = $map->dedis()->orderBy('Score')->get()->keyBy('Player');
    }

    public static function beginMap(Map $map)
    {
        $records = self::getChallengeRecords($map);

        if ($records && $records->count() > 0) {
            //Wipe all dedis for current map
            $map->dedis()->where('New', '=', 0)->delete();

            $insert = $records->map(function ($record) use ($map) {
                $player = Player::firstOrCreate(['Login' => $record->login], [
                    'NickName' => $record->nickname,
                    'MaxRank'  => $record->max_rank,
                ]);

                return [
                    'Map'         => $map->id,
                    'Player'      => $player->id ?? player($record->login)->id,
                    'Score'       => $record->score,
                    'Rank'        => $record->rank,
                    'Checkpoints' => $record->checkpoints,
                ];
            })->filter();

            Dedi::insert($insert->toArray());
            self::cacheDedis($map);
        } else {
            self::$dedis = collect();
        }

        self::cacheDedisJson();
        self::sendUpdatedDedis();

        Log::logAddLine('Dedimania', "Loaded records for map $map #" . $map->id);
    }

    private static function cacheDedisJson()
    {
        self::$dedisJson = self::$dedis->map(function (Dedi $dedi) {
            $checkpoints = collect(explode(',', $dedi->Checkpoints));
            $checkpoints = $checkpoints->map(function ($time) {
                return intval($time);
            });

            return [
                'rank'  => $dedi->Rank,
                'cps'   => $checkpoints,
                'score' => $dedi->Score,
                'name'  => str_replace('{', '\u007B', str_replace('}', '\u007D', $dedi->player->NickName)),
                'login' => $dedi->player->Login,
            ];
        })->toJson();
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 8000) {
            //ignore times under 8 seconds
            return;
        }

        $map              = MapController::getCurrentMap();
        $nextBetterRecord = $map->dedis()->where('Score', '<=', $score)->orderByDesc('Score')->first();
        $newRank          = $nextBetterRecord ? $nextBetterRecord->Rank + 1 : 1;

        $saveRecord = $newRank <= self::$maxRank;

        if (!$saveRecord) {
            //check for dedimania premium
            $saveRecord = $newRank <= $player->MaxRank;
        }

        if (self::$dedis->has($player->id)) {
            $oldRecord = self::$dedis->get($player->id);
            $oldRank   = $oldRecord->Rank;

            $chatMessage = chatMessage()
                ->setIcon('')
                ->setColor(config('colors.dedi'));

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ', $oldRecord)->sendAll();

                return;
            }

            if (!$saveRecord) {
                return;
            }

            if ($oldRecord->Score < $score) {
                return;
            }

            $map->dedis()->updateOrCreate(['Player' => $player->id], [
                'Score'       => $score,
                'Checkpoints' => $checkpoints,
                'Rank'        => $newRank,
                'New'         => 1,
            ]);

            self::fixRanks($map);

            $newRecord = $map->dedis()->wherePlayer($player->id)->first();
            $newRank   = $newRecord->Rank;
            $diff      = $oldRecord->Score - $score;

            if ($newRank == 1) {
                //Ghost replay is needed for 1. dedi
                self::saveGhostReplay($newRecord);
            }

            if ($oldRank == $newRank) {
                $chatMessage->setParts($player, ' secured his/her ', $newRecord, ' (' . $oldRank . '. -' . formatScore($diff) . ')');
            } else {
                $chatMessage->setParts($player, ' gained the ', $newRecord, ' (' . $oldRank . '. -' . formatScore($diff) . ')');
            }

            $chatMessage->sendAll();

            self::cacheDedis($map);
            self::cacheDedisJson();
            self::sendUpdatedDedis();
        } else {
            if (!$saveRecord) {
                return;
            }

            $map->dedis()->updateOrCreate(['Player' => $player->id], [
                'Score'       => $score,
                'Checkpoints' => $checkpoints,
                'Rank'        => $newRank,
                'New'         => 1,
            ]);

            self::fixRanks($map);

            $newRecord = $map->dedis()->wherePlayer($player->id)->first();
            $newRank   = $newRecord->Rank;

            if ($newRank == 1) {
                //Ghost replay is needed for 1. dedi
                self::saveGhostReplay($newRecord);
            }

            chatMessage($player, ' gained the ', $newRecord)
                ->setIcon('')
                ->setColor(config('colors.dedi'))
                ->sendAll();

            self::cacheDedis($map);
            self::cacheDedisJson();
            self::sendUpdatedDedis();
        }
    }

    private static function fixRanks(Map $map)
    {
        Database::getConnection()->statement('SET @rank=0');
        Database::getConnection()->statement('UPDATE `dedi-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = ' . $map->id . ' ORDER BY `Score`');
    }

    private static function saveGhostReplay(Model $dedi)
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