<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Models\Player;
use esc\Models\Stats;

class EventController
{
    /**
     * @param $executedCallbacks
     * @throws \Exception
     */
    public static function handleCallbacks($executedCallbacks)
    {
        foreach ($executedCallbacks as $callback) {
            $name      = $callback[0];
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

                case 'ManiaPlanet.ModeScriptCallbackArray':
                    ModeScriptEventController::handleModeScriptCallbacks($callback);
                    break;

                default:
                    echo "Calling unhandled: $name \n";
                    break;
            }

//            Hook::fire($name, $arguments);
        }

    }

    /**
     * @param $playerInfos
     */
    private static function mpPlayerInfoChanged($playerInfos)
    {
        foreach ($playerInfos as $playerInfo) {
            $login    = $playerInfo['Login'];
            $nickname = $playerInfo['NickName'];
            $playerId = $playerInfo['PlayerId'];

            $player = Player::find($login);

            if ($player) {
                $player->update([
                    'NickName'  => $nickname,
                    'player_id' => $playerId
                ]);
            } else {
                $playerId = Player::insertGetId([
                    'Login'     => $login,
                    'NickName'  => $nickname,
                    'player_id' => $playerId
                ]);

                Stats::create([
                    'Player' => $playerId,
                    'Visits' => 1
                ]);
            }
        }
    }

    /**
     * @param $playerInfo
     * @throws \Exception
     */
    private static function mpPlayerConnect($playerInfo)
    {
        if (count($playerInfo) == 2 && is_string($playerInfo[0])) {
            $login = $playerInfo[0];

            try {
                $player = Player::findOrFail($login);
                Hook::fire('PlayerConnect', $player);
            } catch (\Exception $e) {
                Log::logAddLine('EventController', "Error: Player ($login) not found!");
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }

    /**
     * @param $data
     * @throws \Exception
     */
    private static function mpPlayerChat($data)
    {
        if (count($data) == 4 && is_string($data[1])) {
            $login = $data[1];
            $text  = $data[2];

            try {
                $player = Player::findOrFail($login);
                Hook::fire('PlayerChat', $player, $text, false);
            } catch (\Exception $e) {
                Log::logAddLine('EventController', "Error: Player ($login) not found!");
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }

    /**
     * @param $arguments
     * @throws \Exception
     */
    private static function mpPlayerDisconnect($arguments)
    {
        if (count($arguments) == 2 && is_string($arguments[0])) {
            $login = $arguments[0];

            try {
                $player = Player::findOrFail($login);
                Player::whereLogin($login)->update(['player_id' => 0]);
                Hook::fire('PlayerDisconnect', $player, 0);
            } catch (\Exception $e) {
                Log::logAddLine('EventController', "Error: Player ($login) not found!");
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }
}