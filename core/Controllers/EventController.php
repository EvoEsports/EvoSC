<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\Map;
use esc\Models\Player;
use esc\Models\Stats;

class EventController implements ControllerInterface
{
    /**
     * @param $executedCallbacks
     *
     * @throws \Exception
     */
    public static function handleCallbacks($executedCallbacks)
    {
        foreach ($executedCallbacks as $callback) {
            $name      = $callback[0];
            $arguments = $callback[1];

            if (isVerbose()) {
                Log::logAddLine('EventController', "Call $name", true);
            }

            switch ($name) {
                case 'ManiaPlanet.PlayerInfoChanged':
                    self::mpPlayerInfoChanged($arguments);
                    break;

                case 'ManiaPlanet.PlayerConnect':
                    self::mpPlayerConnect($arguments);
                    break;

                case 'ManiaPlanet.PlayerDisconnect':
                    self::mpPlayerDisconnect($arguments);
                    break;

                case 'ManiaPlanet.PlayerChat':
                    self::mpPlayerChat($arguments);
                    break;

                case 'ManiaPlanet.BeginMap':
                    self::mpBeginMap($arguments);
                    break;

                case 'ManiaPlanet.EndMap':
                    self::mpEndMap($arguments);
                    break;

                case 'ManiaPlanet.BeginMatch':
                    Hook::fire('BeginMatch');
                    break;

                case 'ManiaPlanet.EndMatch':
                    Hook::fire('EndMatch');
                    break;

                case 'ManiaPlanet.PlayerManialinkPageAnswer':
                    self::mpPlayerManialinkPageAnswer($arguments);
                    break;

                case 'ManiaPlanet.ModeScriptCallbackArray':
                    ModeScriptEventController::handleModeScriptCallbacks($callback);
                    break;

                default:
                    break;
            }

            Hook::fire($name, $arguments);
        }

    }

    /**
     * @param $playerInfos
     *
     * @throws \Maniaplanet\DedicatedServer\InvalidArgumentException
     */
    private static function mpPlayerInfoChanged($playerInfos)
    {
        foreach ($playerInfos as $playerInfo) {
            $login           = $playerInfo['Login'];
            $nickname        = $playerInfo['NickName'];
            $playerId        = $playerInfo['PlayerId'];
            $spectatorStatus = $playerInfo['SpectatorStatus'];
            $countryPath     = Server::rpc()->getDetailedPlayerInfo($login)->path;

            $player = Player::find($login);

            if (!$player) {
                Player::create(['Login' => $login]);
                $player = Player::find($login);
            }

            $specTargetId = $player->spectator_status->currentTargetId;
            $wasSpectator = $specTargetId > 0;

            if ($player) {
                $player->update([
                    'NickName'         => $nickname,
                    'player_id'        => $playerId,
                    'spectator_status' => $spectatorStatus,
                    'path'             => $countryPath,
                ]);

                Hook::fire('PlayerInfoChanged', $player);
            } else {
                $playerId = Player::insertGetId([
                    'Login'            => $login,
                    'NickName'         => $nickname,
                    'player_id'        => $playerId,
                    'spectator_status' => $spectatorStatus,
                    'path'             => $countryPath,
                ]);

                Stats::create([
                    'Player' => $playerId,
                    'Visits' => 1,
                ]);

                $player = Player::whereId($playerId)->first();

                Hook::fire('PlayerInfoChanged', $player);
            }

            $targetId = $player->spectator_status->currentTargetId;

            if ($targetId > 0) {
                $target = Player::wherePlayerId($targetId)->first();

                if ($target instanceof Player) {
                    Hook::fire('SpecStart', $player, $target);
                }
            } else {
                if ($wasSpectator) {
                    $target = Player::wherePlayerId($specTargetId)->first();

                    if ($target instanceof Player) {
                        Hook::fire('SpecStop', $player, $target);
                    }
                }
            }
        }
    }

    /**
     * @param $playerInfo
     *
     * @throws \Exception
     */
    private static function mpPlayerConnect($playerInfo)
    {
        if (count($playerInfo) == 2 && is_string($playerInfo[0])) {
            $login = $playerInfo[0];

            try {
                $player = Player::findOrFail($login);
            } catch (\Exception $e) {
                Log::logAddLine('ERROR', "mpPlayerConnect: Player ($login) not found!");
            }

            try {
                Hook::fire('PlayerConnect', $player);
            } catch (\Exception $e) {
                Log::logAddLine('PlayerConnect', "Error: " . $e->getMessage());
                createCrashReport($e);
            }
        } else {
            throw new \Exception('Malformed callback in mpPlayerConnect');
        }
    }

    /**
     * @param $data
     *
     * @throws \Exception
     */
    private static function mpPlayerChat($data)
    {
        if (count($data) == 4 && is_string($data[1])) {
            $login = $data[1];
            $text  = $data[2];

            try {
                $player = Player::findOrFail($login);
            } catch (\Exception $e) {
                Log::logAddLine('mpPlayerChat', "Error: Player ($login) not found!");
            }

            try {
                Hook::fire('PlayerChat', $player, $text, false);
            } catch (\Exception $e) {
                Log::logAddLine('PlayerChat', "Error: " . $e->getMessage());
                createCrashReport($e);
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }

    /**
     * @param $arguments
     *
     * @throws \Exception
     */
    private static function mpPlayerDisconnect($arguments)
    {
        if (count($arguments) == 2 && is_string($arguments[0])) {
            $login = $arguments[0];

            try {
                $player = Player::findOrFail($login);
            } catch (\Exception $e) {
                Log::logAddLine('mpPlayerDisconnect', "Error: Player ($login) not found!");
            }

            try {
                Player::whereLogin($login)->update(['player_id' => 0]);
                Hook::fire('PlayerDisconnect', $player, 0);
            } catch (\Exception $e) {
                Log::logAddLine('PlayerDisconnect', "Error: " . $e->getMessage());
                createCrashReport($e);
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }

    /**
     * @param $arguments
     *
     * @throws \Exception
     */
    private static function mpBeginMap($arguments)
    {
        if (count($arguments[0]) == 16 && is_string($arguments[0]['UId'])) {
            $mapUid = $arguments[0]['UId'];

            try {
                $map = Map::where('uid', $mapUid)->first();
            } catch (\Exception $e) {
                Log::logAddLine('mpBeginMap', "Error: Map ($mapUid) not found!");
            }

            try {
                Hook::fire('BeginMap', $map);
            } catch (\Exception $e) {
                Log::logAddLine('Hook', "Error: " . $e->getMessage());
                createCrashReport($e);
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }

    /**
     * @param $arguments
     *
     * @throws \Exception
     */
    private static function mpEndMap($arguments)
    {
        if (count($arguments[0]) == 16 && is_string($arguments[0]['UId'])) {
            $mapUid = $arguments[0]['UId'];

            try {
                $map = Map::where('uid', $mapUid)->first();
            } catch (\Exception $e) {
                Log::logAddLine('mpEndMap', "Error: Map ($mapUid) not found!");
            }

            try {
                Hook::fire('EndMap', $map);
            } catch (\Exception $e) {
                Log::logAddLine('Hook', "Error: " . $e->getMessage());
                createCrashReport($e);
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }

    /**
     * @param $arguments
     *
     * @throws \Exception
     */
    private static function mpPlayerManialinkPageAnswer($arguments)
    {
        if (count($arguments) == 4 && is_string($arguments[1]) && is_string($arguments[2])) {
            $login = $arguments[1];

            try {
                $player = Player::findOrFail($login);
            } catch (\Exception $e) {
                Log::logAddLine('mpPlayerManialinkPageAnswer', "Error: Player ($login) not found!");
            }

            try {
                ManiaLinkEvent::call($player, $arguments[2]);
            } catch (\Exception $e) {
                Log::logAddLine('ManiaLinkEvent:' . $arguments[2], "Error: " . $e->getMessage());
                createCrashReport($e);
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }

    /**
     * Method called on boot.
     *
     * @return mixed
     */
    public static function init()
    {
    }
}