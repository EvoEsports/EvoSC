<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\Cache;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use Exception;

/**
 * Class EventController
 *
 * @package EvoSC\Controllers
 */
class EventController implements ControllerInterface
{
    /**
     * Method called on controller-boot.
     */
    public static function init()
    {
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
    }

    /**
     * @param $executedCallbacks
     *
     * @throws Exception
     */
    public static function handleCallbacks($executedCallbacks)
    {
        foreach ($executedCallbacks as $callback) {
            $name = $callback[0];
            $arguments = $callback[1];

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
                    self::setMatchStartTime();
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

                case 'ManiaPlanet.Echo':
                    Log::write(json_encode($callback));
                    break;

                default:
                    break;
            }

            Hook::fire($name, $arguments);
        }

    }

    /**
     * @param $playerInfos
     */
    private static function mpPlayerInfoChanged($playerInfos)
    {
        foreach ($playerInfos as $playerInfo) {
            $player = player($playerInfo['Login']);

            $player->spectator_status = $playerInfo['SpectatorStatus'];
            $player->player_id = $playerInfo['PlayerId'];
            $player->ubisoft_name = $playerInfo['NickName'];
            if($player->isDirty()){
                $player->save();
            }
            PlayerController::putPlayer($player);
        }
    }

    /**
     * @param $data
     *
     * @throws Exception
     */
    private static function mpPlayerChat($data)
    {
        if (count($data) == 4 && is_string($data[1])) {
            $login = $data[1];
            $text = $data[2];

            if ($login === config('server.login')) {
                return;
            }

            $parts = explode(' ', $text);

            if (ChatCommand::has($parts[0])) {
                ChatCommand::get($parts[0])->execute(player($login), $text);

                return;
            }

            if (substr($text, 0, 1) == '/' || substr($text, 0, 2) == '//') {
                warningMessage('Invalid chat-command entered. See ', secondary('/help'), ' for all commands.')->send(player($login));

                return;
            }

            if (collect(Server::getIgnoreList())->contains('login', $login)) {
                //Player is muted
                warningMessage('You are muted.')->send(player($login));

                return;
            }

            try {
                Hook::fire('PlayerChat', player($login), $text);
            } catch (Exception $e) {
                Log::write("Error: " . $e->getMessage());
            }

            try {
                ChatController::playerChat(player($login), $text);
            } catch (Exception $e) {
                Log::write("Error: " . $e->getMessage());
            }
        } else {
            throw new Exception('Malformed callback');
        }
    }

    /**
     * @param $playerInfo
     *
     * @throws Exception
     */
    private static function mpPlayerConnect($playerInfo)
    {
        if (count($playerInfo) == 2 && is_string($playerInfo[0])) {
            $details = Server::getDetailedPlayerInfo($playerInfo[0]);

            $player = Player::updateOrCreate(['Login' => $playerInfo[0]], [
                'NickName' => $details->nickName,
                'path' => $details->path,
                'player_id' => $details->playerId,
            ]);

            if (isTrackmania() && Cache::has('nicknames/' . $playerInfo[0])) {
                $name = Cache::get('nicknames/' . $playerInfo[0]);
                PlayerController::setName($player, $name, true, true);
            }

            Hook::fire('PlayerConnect', $player);
        } else {
            throw new Exception('Malformed callback in mpPlayerConnect');
        }
    }

    /**
     * @param $arguments
     *
     * @throws Exception
     */
    private static function mpPlayerDisconnect($arguments)
    {
        if (count($arguments) == 2 && is_string($arguments[0])) {
            Hook::fire('PlayerDisconnect', player($arguments[0]), 0);
        } else {
            throw new Exception('Malformed callback');
        }
    }

    /**
     * @param $arguments
     *
     * @throws Exception
     */
    private static function mpBeginMap($arguments)
    {
        if (count($arguments[0]) == 16 && is_string($arguments[0]['UId'])) {
            $mapUid = $arguments[0]['UId'];

            $map = Map::whereUid($mapUid)->get()->first();

            if ($map == null) {
                Log::error("Map with UID $mapUid not found in database!");
            }

            MapController::setCurrentMap($map);

            try {
                Hook::fire('BeginMap', $map);
            } catch (Exception $e) {
                Log::write("Error: " . $e->getMessage());
            }
        } else {
            throw new Exception('Malformed callback');
        }
    }

    /**
     * @param $arguments
     *
     * @throws Exception
     */
    private static function mpEndMap($arguments)
    {
        if (count($arguments[0]) == 16 && is_string($arguments[0]['UId'])) {
            $map = Map::getByUid($arguments[0]['UId']);

            try {
                Hook::fire('EndMap', $map);
            } catch (Exception $e) {
                Log::write("Error: " . $e->getMessage());
            }
        } else {
            throw new Exception('Malformed callback');
        }
    }

    /**
     * @param $arguments
     *
     * @throws Exception
     */
    private static function mpPlayerManialinkPageAnswer($arguments)
    {
        if (count($arguments) == 4 && is_string($arguments[1]) && is_string($arguments[2])) {
            try {
                ManiaLinkEvent::call(player($arguments[1]), $arguments[2], $arguments[3]);
            } catch (Exception $e) {
                Log::write("Error: " . $e->getMessage());
            }
        } else {
            throw new Exception('Malformed callback');
        }
    }

    /**
     * writes round start time to disk
     */
    private static function setMatchStartTime()
    {
        $file = cacheDir('round_start_time.txt');
        File::put($file, time());
    }
}