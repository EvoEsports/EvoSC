<?php

namespace esc\Modules;


use esc\Classes\Database;
use esc\Classes\DB;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
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

    private static $offlineMode;

    public function __construct()
    {
        if (!config('dedimania.enabled')) {
            return;
        }

        self::$offlineMode = false;

        //Check for session key
        if (!self::getSessionKey()) {
            //There is no existing session

            if (!DedimaniaApi::openSession()) {
                //Failed to start session
                self::$offlineMode = true;
            }
        } else {
            //Session exists

            if (!self::checkSession()) {
                //session expired

                if (!DedimaniaApi::openSession()) {
                    //Failed to start session
                    self::$offlineMode = true;
                } else {
                    Log::info('Dedimania started. Session: '.self::getSessionLastUpdated().', Max-Rank: '.self::$maxRank.'.');
                }
            } else {
                Log::info('Dedimania started. Session: '.self::getSessionLastUpdated().', Max-Rank: '.self::$maxRank.'.');
            }
        }

        //Session exists and is not expired
        self::$enabled = true;

        //Add hooks
        Hook::add('PlayerConnect', [DedimaniaApi::class, 'playerConnect']);
        Hook::add('PlayerConnect', [self::class, 'showManialink']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMatch', [self::class, 'endMatch']);

        //Check if session is still valid each 5 seconds
        Timer::create('dedimania.check_session', [self::class, 'checkSessionStillValid'], '5m');
        Timer::create('dedimania.report_players', [self::class, 'reportConnectedPlayers'], '5m');

        ManiaLinkEvent::add('dedis.show', [self::class, 'showDedisTable']);
    }

    public static function reportConnectedPlayers()
    {
        $map = MapController::getCurrentMap();
        $data = self::updateServerPlayers($map);

        if ($data && !isset($data->params->param->value->boolean)) {
            Log::write('Failed to report connected players. Trying again in 5 minutes.');
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

    public static function showManialink(Player $player)
    {
        if (self::$offlineMode) {
            warningMessage('Unfortunately Dedimania is offline, new records will not be visible before it comes online again.')->send($player);
        }

        $dedisJson = self::$dedisJson;

        Template::show($player, 'dedimania-records.update', compact('dedisJson'));
        Template::show($player, 'dedimania-records.manialink');
    }

    public static function showDedisTable(Player $player)
    {
        $map = MapController::getCurrentMap();
        $records = $map->dedis()->orderBy('Score')->get();

        RecordsTable::show($player, $map, $records, 'Dedimania Records');
    }

    public static function sendUpdatedDedis()
    {
        $dedisJson = self::$dedisJson;
        Template::showAll('dedimania-records.update', compact('dedisJson'));
    }

    private static function cacheDedis(Map $map)
    {
        self::$dedis = DB::table('dedi-records')
            ->where('Map', '=', $map->id)
            ->orderBy('Score')
            ->get()
            ->keyBy('Player');
    }

    private static function cacheDedisJson()
    {
        $playerIds = self::$dedis->pluck('Player');
        $players = DB::table('players')->whereIn('id', $playerIds)->get()->keyBy('id');

        self::$dedisJson = self::$dedis->map(function ($dedi) use ($players) {
            $checkpoints = collect(explode(',', $dedi->Checkpoints));
            $checkpoints = $checkpoints->transform(function ($time) {
                return intval($time);
            });

            $player = $players->get($dedi->Player);

            return [
                'rank' => $dedi->Rank,
                'cps' => $checkpoints,
                'score' => $dedi->Score,
                'name' => str_replace('{', '\u007B', str_replace('}', '\u007D', $player->NickName)),
                'login' => $player->Login,
            ];
        })->toJson();
    }

    public static function beginMap(Map $map)
    {
        $records = self::getChallengeRecords($map);

        if (!$records && self::$offlineMode) {
            $records = DB::table('dedi-records')
                ->where('Map', '=', $map->id)
                ->get()
                ->transform(function (Dedi $dedi) {
                    $record = collect();
                    $record->login = $dedi->player->Login;
                    $record->nickname = ml_escape($dedi->player->NickName);
                    $record->score = $dedi->Score;
                    $record->rank = $dedi->Rank;
                    $record->max_rank = $dedi->player->MaxRank;
                    $record->checkpoints = $dedi->Checkpoints;
                    return $record;
                });
        }

        if ($records && $records->count() > 0) {
            //Wipe all dedis for current map
            DB::table('dedi-records')
                ->where('Map', '=', $map->id)
                ->where('New', '=', 0)
                ->delete();

            $insert = $records->transform(function ($record) use ($map) {
                $player = DB::table('players')->updateOrInsert(['Login' => $record->login], [
                    'NickName' => $record->nickname,
                    'MaxRank' => $record->max_rank,
                ]);

                return [
                    'Map' => $map->id,
                    'Player' => $player->id ?? player($record->login)->id,
                    'Score' => $record->score,
                    'Rank' => $record->rank,
                    'Checkpoints' => $record->checkpoints,
                ];
            })->filter();

            DB::table('dedi-records')->insert($insert->toArray());
            self::cacheDedis($map);
        } else {
            self::$dedis = collect();
        }

        self::cacheDedisJson();
        self::sendUpdatedDedis();

        Log::write("Loaded records for map $map #".$map->id);
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 8000) {
            //ignore times under 8 seconds
            return;
        }

        $map = MapController::getCurrentMap();
        $nextBetterRecord = DB::table('dedi-records')
            ->where('Map', '=', $map->id)
            ->where('Score', '<=', $score)
            ->orderByDesc('Score')
            ->first();

        $newRank = $nextBetterRecord ? $nextBetterRecord->Rank + 1 : 1;
        $playerHasDedi = self::$dedis->has($player->id);

        if (!$playerHasDedi) {
            if ($newRank > self::$maxRank) {
                var_dump("New rank is above server max rank.");
                //check for dedimania premium
                if ($newRank > $player->MaxRank) {
                    var_dump("New rank is above player max rank.");
                    return;
                }
            }
        }

        var_dump("Proceed.");

        if ($playerHasDedi) {
            $oldRecord = self::$dedis->get($player->id);
            $oldRank = $oldRecord->Rank;

            $chatMessage = chatMessage()
                ->setIcon('')
                ->setColor(config('colors.dedi'));

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ', $oldRecord)->sendAll();

                return;
            }

            if ($oldRecord->Score < $score) {
                return;
            }

            DB::table('dedi-records')
                ->where('Map', '=', $map->id)
                ->updateOrInsert(['Player' => $player->id], [
                    'Score' => $score,
                    'Checkpoints' => $checkpoints,
                    'Rank' => $newRank,
                    'New' => 1,
                ]);

            self::fixRanks($map);

            $newRecord = $map->dedis()->where('Player', '=', $player->id)
                ->first();

            $diff = $oldRecord->Score - $score;

            if ($newRank == 1) {
                //Ghost replay is needed for 1. dedi
                self::saveGhostReplay($newRecord);
            }


            if ($oldRank == $newRecord->Rank) {
                $chatMessage->setParts($player, ' secured his/her ', $newRecord,
                    ' ('.$oldRank.'. -'.formatScore($diff).')');
            } else {
                $chatMessage->setParts($player, ' gained the ', $newRecord,
                    ' ('.$oldRank.'. -'.formatScore($diff).')');
            }

            if ($newRecord->Rank <= config('dedimania.echo-top', 100)) {
                $chatMessage->sendAll();
            }

            self::cacheDedis($map);
            self::cacheDedisJson();
            self::sendUpdatedDedis();
        } else {
            $map->dedis()->updateOrCreate(['Player' => $player->id], [
                'Score' => $score,
                'Checkpoints' => $checkpoints,
                'Rank' => $newRank,
                'New' => 1,
            ]);

            self::fixRanks($map);

            $newRecord = $map->dedis()->where('Player', '=', $player->id)
                ->first();

            if ($newRecord->Rank == 1) {
                //Ghost replay is needed for 1. dedi
                self::saveGhostReplay($newRecord);
            }

            if ($newRank <= config('dedimania.echo-top', 100)) {
                chatMessage($player, ' gained the ', $newRecord)
                    ->setIcon('')
                    ->setColor(config('colors.dedi'))
                    ->sendAll();
            }

            self::cacheDedis($map);
            self::cacheDedisJson();
            self::sendUpdatedDedis();
        }
    }

    private static function fixRanks(Map $map)
    {
        Database::getConnection()->statement('SET @rank=0');
        Database::getConnection()->statement('UPDATE `dedi-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = '.$map->id.' ORDER BY `Score`');
    }

    private static function formatRecord(\stdClass $record): string
    {

    }

    private static function saveGhostReplay(Model $dedi)
    {
        $oldGhostReplay = $dedi->ghost_replay;

        if ($oldGhostReplay && File::exists($oldGhostReplay)) {
            unlink($oldGhostReplay);
        }

        $ghostFile = sprintf('%s_%s_%d', stripAll($dedi->player->Login), stripAll($dedi->map->Name), $dedi->Score);

        try {
            $saved = Server::saveBestGhostsReplay($dedi->player->Login, 'Ghosts/'.$ghostFile);

            if ($saved) {
                $dedi->update(['ghost_replay' => $ghostFile]);
            }
        } catch (\Exception $e) {
            Log::error('Could not save ghost: '.$e->getMessage());
        }
    }

    /**
     * @return bool
     */
    public static function isOfflineMode(): bool
    {
        return self::$offlineMode;
    }
}