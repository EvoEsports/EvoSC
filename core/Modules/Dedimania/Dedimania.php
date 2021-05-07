<?php

namespace EvoSC\Modules\Dedimania;


use EvoSC\Classes\Cache;
use EvoSC\Classes\DB;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Classes\Timer;
use EvoSC\Classes\Utility;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\Dedimania\Models\Dedi;
use EvoSC\Modules\RecordsTable\RecordsTable;
use Exception;

class Dedimania extends DedimaniaApi implements ModuleInterface
{
    const TABLE = 'dedi-records';

    private static bool $offlineMode = false;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        global $__ManiaPlanet;
        if (!$__ManiaPlanet) {
            return;
        }

        //Add hooks
        Hook::add('PlayerConnect', [DedimaniaApi::class, 'playerConnect']);
        Hook::add('PlayerConnect', [self::class, 'showManialink']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMatch', [self::class, 'endMatch']);

        //Check if session is still valid each 5 seconds
        Timer::create('dedimania.check_session', [self::class, 'checkSessionStillValid'], '5m', true);
        Timer::create('dedimania.report_players', [self::class, 'reportConnectedPlayers'], '5m', true);

        ManiaLinkEvent::add('dedis.show', [self::class, 'showDedisTable']);
    }

    public function stop()
    {
        Cache::put('dedimania_hot_reload', '', now()->addMinute());
    }

    public function __construct()
    {
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
                    Log::info('Dedimania started. Session: ' . self::getSessionLastUpdated() . ', Max-Rank: ' . self::$maxRank . '.');
                }
            } else {
                Log::info('Dedimania started. Session: ' . self::getSessionLastUpdated() . ', Max-Rank: ' . self::$maxRank . '.');
            }
        }

        //Session exists and is not expired
        self::$enabled = true;

        if (!File::dirExists(cacheDir('vreplays'))) {
            File::makeDir(cacheDir('vreplays'));
        }
    }

    public static function reportConnectedPlayers()
    {
        $map = MapController::getCurrentMap();
        self::updateServerPlayers($map, ModeController::isTimeAttackType());
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
    }

    /**
     *
     */
    public static function endMatch()
    {
        $map = MapController::getCurrentMap();
        self::setChallengeTimes($map, ModeController::isTimeAttackType());
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showManialink(Player $player)
    {
        if (self::$offlineMode) {
            warningMessage('Unfortunately Dedimania is offline, new records will not be saved before it comes online again.')->send($player);
        }

        self::sendUpdatedDedis($player);
        Template::show($player, 'Dedimania.manialink');
    }

    /**
     * @param Player $player
     */
    public static function showDedisTable(Player $player)
    {
        $map = MapController::getCurrentMap();

        $records = DB::table(self::TABLE)
            ->select(['Rank', 'dedi-records.Score as Score', 'NickName', 'Login', 'Player', 'players.id as id'])
            ->where('Map', '=', $map->id)
            ->leftJoin('players', 'players.id', '=', 'dedi-records.Player')
            ->orderBy('Rank')
            ->get();

        RecordsTable::show($player, $map, $records, 'Dedimania Records');
    }

    /**
     * @param Player|null $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendUpdatedDedis(Player $player = null)
    {
        Utility::sendRecordsChunk(self::TABLE, 'dedi-records', 'Dedimania.update', $player);
    }

    /**
     * @param Map $map
     */
    public static function beginMap(Map $map)
    {
        if(Cache::has('dedimania_hot_reload')){
            Cache::forget('dedimania_hot_reload');
            return;
        }

        self::getChallengeRecords($map, ModeController::isTimeAttackType());
    }

    /**
     * @param Player $player
     * @param int $score
     * @param string $checkpoints
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 8000) {
            //ignore times under 8 seconds
            return;
        }

        $map = MapController::getCurrentMap();
        $newRank = Utility::getNextBetterRank(self::TABLE, $map->id, $score);

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
                ->setColor(config('dedimania.text-color'));

            if ($oldRecord->Score < $score) {
                return;
            }

            if ($oldRecord->Score == $score) {
                $chatMessage->setParts($player, ' equaled his/her ', secondary($newRank . '.'), ' dedimania record ', secondary(formatScore($score)))->sendAll();

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

                $chatMessage->setParts($player, ' secured his/her ', secondary($newRank . '.'), ' dedimania record ', secondary(formatScore($score) . ' (' . $oldRank . '. -' . formatScore($diff) . ')'),)->sendAll();
            } else {
                DB::table('dedi-records')
                    ->where('Map', '=', $map->id)
                    ->whereBetween('Rank', [$newRank, $oldRank])
                    ->increment('Rank');

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
                    secondary($newRank . '.'), ' dedimania record ', secondary(formatScore($score) . ' (' . $oldRank . '. -' . formatScore($diff) . ')'))->sendAll();
            }

            if ($newRank == 1) {
                //Ghost replay is needed for 1. dedi
                self::saveGhostReplay($player->id, $player->Login, $map->id);
            }

            self::sendUpdatedDedis();
        } else {
            DB::table('dedi-records')
                ->where('Map', '=', $map->id)
                ->where('Rank', '>=', $newRank)
                ->increment('Rank');

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

            if ($newRank == 1) {
                //Ghost replay is needed for 1. dedi
                self::saveGhostReplay($player->id, $player->Login, $map->id);
            }

            if ($newRank <= config('dedimania.echo-top', 100)) {
                chatMessage($player, ' gained the ', secondary($newRank . '.'), ' dedimania record ', secondary(formatScore($score)))
                    ->setIcon('')
                    ->setColor(config('dedimania.text-color'))
                    ->sendAll();
            }

            self::sendUpdatedDedis();
        }
    }

    /**
     * @param Player $player
     * @param Map $map
     */
    private static function saveVReplay(Player $player, Map $map)
    {
        $login = $player->Login;
        $vreplay = Server::getValidationReplay($login);

        if ($vreplay) {
            file_put_contents(cacheDir('vreplays/' . $login . '_' . $map->uid), $vreplay);
        }
    }

    /**
     * @param int $playerId
     * @param string $playerLogin
     * @param int $mapId
     */
    private static function saveGhostReplay(int $playerId, string $playerLogin, int $mapId)
    {
        $dedi = Dedi::wherePlayer($playerId)->whereMap($mapId)->first();

        if ($dedi->ghost_replay && File::exists($dedi->ghost_replay)) {
            File::delete($dedi->ghost_replay);
        }

        $ghostFile = evo_str_slug(sprintf('%s_%s', $dedi->player->Login, $dedi->map->uid));

        try {
            if (Server::saveBestGhostsReplay($dedi->player->Login, 'Ghosts/' . $ghostFile)) {
                $dedi->update(['ghost_replay' => $ghostFile]);
            } else {
                Log::error('Failed to save ghost for ' . $dedi->player->Login . " [" . $dedi->map->uid . "]");
            }
        } catch (Exception $e) {
            Log::errorWithCause('Could not save ghost', $e);
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
