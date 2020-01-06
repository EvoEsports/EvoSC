<?php

namespace esc\Modules;


use esc\Classes\Cache;
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

        if (!File::dirExists(cacheDir('vreplays'))) {
            File::makeDir(cacheDir('vreplays'));
        }

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

        self::showRecords($player);
        Template::show($player, 'dedimania-records.manialink');
    }

    public static function showRecords(Player $player)
    {
        $top = config('dedimania.show-top', 3);
        $fill = config('dedimania.rows', 15);
        $map = MapController::getCurrentMap();

        $record = DB::table('dedi-records')
            ->where('Map', '=', $map->id)
            ->where('Player', '=', $player->id)
            ->first();

        if ($record) {
            $start = $record->Rank - floor($fill / 2);
            $end = $record->Rank + ceil($fill / 2);
            $addAfter = 0;

            if ($start <= $top) {
                $start = $top + 1;
                $addAfter += abs($top - $start);
            }

            $records = DB::table('dedi-records')
                ->where('Map', '=', $map->id)
                ->whereBetween('Rank', [$start, $end + $addAfter])
                ->get();
        } else {
            $count = DB::table('dedi-records')->where('Map', '=', $map->id)->count();

            $records = DB::table('dedi-records')
                ->where('Map', '=', $map->id)
                ->whereBetween('Rank', [$count - $fill, $count])
                ->get();
        }

        $records = $records->merge(DB::table('dedi-records')
            ->where('Map', '=', $map->id)
            ->where('Rank', '<=', $top)
            ->get());

        $players = DB::table('players')
            ->whereIn('id', $records->pluck('Player'))
            ->get()
            ->keyBy('id');

        $records->transform(function ($dedi) use ($players) {
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
        });

        $dedisJson = $records->sortBy('rank')->toJson();

        Template::show($player, 'dedimania-records.update', compact('dedisJson'));
    }

    public static function showDedisTable(Player $player)
    {
        $map = MapController::getCurrentMap();
        $records = $map->dedis()->orderBy('Score')->get();

        RecordsTable::show($player, $map, $records, 'Dedimania Records');
    }

    public static function sendUpdatedDedis()
    {
        onlinePlayers()->each([self::class, 'showRecords']);
    }

    public static function beginMap(Map $map)
    {
        $records = self::getChallengeRecords($map);

        if (!$records && self::$offlineMode) {
            $records = $map->dedis->transform(function (Dedi $dedi) {
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
        }

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

        $playerHasDedi = DB::table('dedi-records')
            ->where('Map', '=', $map->id)
            ->where('Player', '=', $player->id)
            ->exists();

        if (!$playerHasDedi) {
            if ($newRank > self::$maxRank) {
                //check for dedimania premium
                if ($newRank > $player->MaxRank) {
                    return;
                }
            }
        }

        if ($playerHasDedi) {
            $oldRecord = DB::table('dedi-records')
                ->where('Map', '=', $map->id)
                ->where('Player', '=', $player->id)
                ->first();

            $oldRank = $oldRecord->Rank;

            $chatMessage = chatMessage()
                ->setIcon('')
                ->setColor(config('colors.dedi'));

            if ($oldRecord->Score < $score) {
                return;
            }

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ',
                    secondary($newRank.'.$').config('colors.dedi').' dedimania record '.secondary(formatScore($score)))->sendAll();

                return;
            }

            $diff = $oldRecord->Score - $score;

            if ($oldRank == $newRank) {
                DB::table('dedi-records')
                    ->updateOrInsert([
                        'Map' => $map->id,
                        'Player' => $player->id
                    ], [
                        'Score' => $score,
                        'Checkpoints' => $checkpoints,
                        'New' => 1,
                    ]);

                self::saveVReplay($player, $map);

                $chatMessage->setParts($player, ' secured his/her ',
                    secondary($newRank.'.$').config('colors.dedi').' dedimania record '.secondary(formatScore($score)),
                    ' ('.$oldRank.'. -'.formatScore($diff).')')->sendAll();
            } else {
                DB::table('dedi-records')
                    ->updateOrInsert([
                        'Map' => $map->id,
                        'Player' => $player->id
                    ], [
                        'Score' => $score,
                        'Checkpoints' => $checkpoints,
                        'Rank' => $newRank,
                        'New' => 1,
                    ]);

                self::saveVReplay($player, $map);

                $chatMessage->setParts($player, ' gained the ',
                    secondary($newRank.'.$').config('colors.dedi').' dedimania record '.secondary(formatScore($score)),
                    ' ('.$oldRank.'. -'.formatScore($diff).')')->sendAll();

                DB::table('dedi-records')
                    ->where('Map', '=', $map->id)
                    ->whereBetween('Rank', [$oldRank, $newRank - 1])
                    ->increment('Rank');
            }

            if ($newRank == 1) {
                //Ghost replay is needed for 1. dedi
                self::saveGhostReplay($map->dedis()->where('Player', '=', $player->id)->first());
            }

            self::sendUpdatedDedis();
        } else {
            DB::table('dedi-records')
                ->updateOrInsert([
                    'Map' => $map->id,
                    'Player' => $player->id
                ], [
                    'Score' => $score,
                    'Checkpoints' => $checkpoints,
                    'Rank' => $newRank,
                    'New' => 1,
                ]);

            self::saveVReplay($player, $map);

            DB::table('dedi-records')
                ->where('Map', '=', $map->id)
                ->where('Rank', '>=', $newRank)
                ->increment('Rank');

            if ($newRank == 1) {
                //Ghost replay is needed for 1. dedi
                self::saveGhostReplay($map->dedis()->where('Player', '=', $player->id)->first());
            }

            if ($newRank <= config('dedimania.echo-top', 100)) {
                chatMessage($player, ' gained the ',
                    secondary($newRank.'.$').config('colors.dedi').' dedimania record '.secondary(formatScore($score)))
                    ->setIcon('')
                    ->setColor(config('colors.dedi'))
                    ->sendAll();
            }

            self::sendUpdatedDedis();
        }
    }

    private static function saveVReplay(Player $player, Map $map)
    {
        $login = $player->Login;
        $vreplay = Server::getValidationReplay($login);

        if ($vreplay) {
            file_put_contents(cacheDir('vreplays/'.$login.'_'.$map->uid), $vreplay);
        }
    }

    private static function saveGhostReplay($dedi)
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