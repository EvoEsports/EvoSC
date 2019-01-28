<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Interfaces\ControllerInterface;
use esc\Models\Player;

class ModeScriptEventController implements ControllerInterface
{
    public static function handleModeScriptCallbacks($modescriptCallbackArray)
    {
        if ($modescriptCallbackArray[0] == 'ManiaPlanet.ModeScriptCallbackArray') {
            self::call($modescriptCallbackArray[1][0], $modescriptCallbackArray[1][1]);
        } else {
            var_dump($modescriptCallbackArray);
        }
    }

    private static function call($callback, $arguments)
    {
        switch ($callback) {
            case 'Trackmania.Scores':
                self::tmScores($arguments);
                break;

            case 'Trackmania.Event.GiveUp':
                self::tmGiveUp($arguments);
                break;

            case 'Trackmania.Event.WayPoint':
                self::tmWayPoint($arguments);
                break;

            case 'Trackmania.Event.StartCountdown':
                self::tmStartCountdown($arguments);
                break;

            case 'Trackmania.Event.StartLine':
                self::tmStartLine($arguments);
                break;

            case 'Trackmania.Event.Stunt':
                self::tmStunt($arguments);
                break;

            case 'Trackmania.Event.OnPlayerAdded':
                self::tmPlayerConnect($arguments);
                break;

            case 'Trackmania.Event.OnPlayerRemoved':
                self::tmPlayerLeave($arguments);
                break;

            default:
                Log::logAddLine('ScriptCallback', "Calling unhandled $callback", isVeryVerbose());
                break;
        }

        Hook::fire($callback, $arguments);
    }

    static function tmScores($arguments)
    {
        Hook::fire('ShowScores', $arguments);
    }

    static function tmGiveUp($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        $player      = Player::find($playerLogin);

        Hook::fire('PlayerFinish', $player, 0, "");
    }

    static function tmWayPoint($arguments)
    {
        $wayPoint = json_decode($arguments[0]);

        $player = Player::find($wayPoint->login);
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
        $player      = Player::find($playerLogin);
        Hook::fire('PlayerStartCountdown', $player);
    }

    static function tmStartLine($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        $player      = Player::find($playerLogin);
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
        $player     = Player::find($playerData->login);
        $player->update(['player_id' => 0, 'spectator_status' => 0]);

        $diff = $player->last_visit->diffForHumans();
        ChatController::message(onlinePlayers(), '_info', $player, ', from ', secondary($player->path) ,' left the server after ', secondary(str_replace(' ago', '', $diff)), ' playtime.');

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

    /**
     * Method called on boot.
     *
     * @return mixed
     */
    public static function init()
    {
    }
}