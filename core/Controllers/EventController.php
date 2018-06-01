<?php

namespace esc\Controllers;


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
            $login = $playerInfo['Login'];

            $player = Player::find($login);

            if (!$player) {
                $playerId = Player::insertGetId([
                    'Login'     => $login,
                    'NickName'  => $playerInfo['NickName'],
                    'player_id' => $playerInfo['PlayerId']
                ]);

                Stats::create([
                    'Player' => $playerId,
                    'Visits' => 1
                ]);
            }
        }
    }
}