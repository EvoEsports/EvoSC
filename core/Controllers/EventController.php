<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Models\Player;
use esc\Models\Stats;
use Illuminate\Support\Facades\DB;

class EventController
{
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

                case 'ManiaPlanet.ModeScriptCallbackArray':
                    ModeScriptEventController::handleModeScriptCallbacks($callback);
                    break;

                default:
                    echo "Calling unhandled: $name \n";
                    break;
            }
        }

    }

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
                $player = Player::firstOrFail($login);
                Hook::fire('PlayerConnect', $player);
            } catch (\Exception $e) {
                Log::logAddLine('EventController', "Error: Player ($login) not found!");
            }
        } else {
            throw new \Exception('Malformed callback');
        }
    }
}