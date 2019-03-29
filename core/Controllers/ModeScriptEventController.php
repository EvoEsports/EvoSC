<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\Player;

class ModeScriptEventController implements ControllerInterface
{
    public static function init()
    {
    }

    public static function handleModeScriptCallbacks($modescriptCallbackArray)
    {
        if ($modescriptCallbackArray[0] == 'ManiaPlanet.ModeScriptCallbackArray') {
            self::call($modescriptCallbackArray[1][0], $modescriptCallbackArray[1][1]);
        } else {
            Log::logAddLine('ModeScriptEventController', 'Modescript callback is not ManiaPlanet.ModeScriptCallbackArray', isVerbose());
            var_dump($modescriptCallbackArray);
        }
    }

    private static function call($callback, $arguments)
    {
        switch ($callback) {
            case 'Trackmania.Scores':
                self::tmScores($arguments);

                return;

            case 'Trackmania.Event.GiveUp':
                self::tmGiveUp($arguments);

                return;

            case 'Trackmania.Event.WayPoint':
                self::tmWayPoint($arguments);

                return;

            case 'Trackmania.Event.StartCountdown':
                self::tmStartCountdown($arguments);

                return;

            case 'Trackmania.Event.StartLine':
                self::tmStartLine($arguments);

                return;

            case 'Trackmania.Event.Stunt':
                self::tmStunt($arguments);

                return;

            case 'Trackmania.Event.OnPlayerAdded':
                // self::tmPlayerConnect($arguments);
                return;

            case 'Trackmania.Event.OnPlayerRemoved':
                // self::tmPlayerLeave($arguments);
                return;

            default:
                Log::logAddLine('ModeScriptEventController', 'Calling unhandled ' . $callback, isVeryVerbose());
                Hook::fire($callback, $arguments);
        }
    }

    static function tmScores($arguments)
    {
        Hook::fire('ShowScores', $arguments);
    }

    static function tmGiveUp($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        $player      = player($playerLogin);

        Hook::fire('PlayerFinish', $player, 0, "");
    }

    static function tmWayPoint($arguments)
    {
        $wayPoint = json_decode($arguments[0]);

        $player = player($wayPoint->login);
        $map    = MapController::getCurrentMap();

        $totalCps = $map->gbx->CheckpointsPerLaps;

        //checkpoint passed
        Hook::fire('PlayerCheckpoint',
            $player,
            $wayPoint->laptime,
            ceil($wayPoint->checkpointinrace / $totalCps),
            count($wayPoint->curlapcheckpoints) - 1
        );

        //player finished
        if ($wayPoint->isendlap) {
            Hook::fire('PlayerFinish',
                $player,
                $wayPoint->laptime,
                self::cpArrayToString($wayPoint->curlapcheckpoints)
            );
        }
    }

    static function tmStartCountdown($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        $player      = player($playerLogin);
        Hook::fire('PlayerStartCountdown', $player);
    }

    static function tmStartLine($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        $player      = player($playerLogin);
        Hook::fire('PlayerStartLine', $player);
    }

    static function tmStunt($arguments)
    {
        //ignore stunts for now
    }

    static function tmPlayerConnect($arguments)
    {
        $playerData = json_decode($arguments[0]);

        //string Login, bool IsSpectator
        if (Player::whereLogin($playerData->login)->get()->isEmpty()) {
            $player = Player::create(['Login' => $playerData->login, 'NickName' => $playerData->login]);
        } else {
            $player = Player::find($playerData->login);
        }

        Hook::fire('PlayerConnect', $player);
    }

    static function tmPlayerLeave($arguments)
    {
        $playerData = json_decode($arguments[0]);
        $player     = player($playerData->login);

        Hook::fire('PlayerDisconnect', $player);
    }

    /**
     * Convert cp array to comma separated string
     *
     * @param array $cps
     *
     * @return string
     */
    private static function cpArrayToString(array $cps)
    {
        return implode(',', $cps);
    }
}