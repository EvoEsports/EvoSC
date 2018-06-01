<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Models\Player;

class ModeScriptEventController
{
    public static function handleModeScriptCallbacks($modescriptCallbackArray)
    {
        $callback  = $modescriptCallbackArray[0];
        $arguments = $modescriptCallbackArray[1];

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
                Log::logAddLine('ScriptCallback', "Calling unhandled $callback", false);
                break;
        }

        Hook::fire($callback, $arguments);
    }

    static function tmScores($arguments)
    {
        $showScoresHooks = HookController::getHooks('ShowScores');
        HookController::fireHookBatch($showScoresHooks, $arguments);
    }

    static function tmGiveUp($arguments)
    {
        $playerFinishHooks = HookController::getHooks('PlayerFinish');

        $playerLogin = json_decode($arguments[0])->login;
        $player      = Player::find($playerLogin);

        HookController::fireHookBatch($playerFinishHooks, $player, 0, "");
    }

    static function tmWayPoint($arguments)
    {
        $playerCheckpointHooks = HookController::getHooks('PlayerCheckpoint');
        $playerFinishHooks     = HookController::getHooks('PlayerFinish');

        $wayPoint = json_decode($arguments[0]);

        $player = Player::find($wayPoint->login);
        $map    = MapController::getCurrentMap();

        $totalCps = $map->NbCheckpoints;

        //checkpoint passed
        HookController::fireHookBatch($playerCheckpointHooks,
            $player,
            $wayPoint->laptime,
            ceil($wayPoint->checkpointinrace / $totalCps),
            count($wayPoint->curlapcheckpoints) - 1
        );

        //player finished
        if ($wayPoint->isendlap) {
            HookController::fireHookBatch($playerFinishHooks,
                $player,
                $wayPoint->laptime,
                self::cpArrayToString($wayPoint->curlapcheckpoints)
            );
        }
    }

    static function tmStartCountdown($arguments)
    {
        $playerStartCountdown = HookController::getHooks('PlayerStartCountdown');
        $playerLogin          = json_decode($arguments[0])->login;
        $player               = Player::find($playerLogin);
        HookController::fireHookBatch($playerStartCountdown, $player);
    }

    static function tmStartLine($arguments)
    {
        $playerStartCountdown = HookController::getHooks('PlayerStartLine');
        $playerLogin          = json_decode($arguments[0])->login;
        $player               = Player::find($playerLogin);
        HookController::fireHookBatch($playerStartCountdown, $player);
    }

    static function tmStunt($arguments)
    {
        //ignore stunts for now
    }

    static function tmPlayerConnect($arguments)
    {
        $playerData         = json_decode($arguments[0]);
        $playerConnectHooks = HookController::getHooks('PlayerConnect');

        //string Login, bool IsSpectator
        if (Player::whereLogin($playerData->login)->get()->isEmpty()) {
            $player = Player::create(['Login' => $playerData->login]);
        } else {
            $player = Player::find($playerData->login);
        }

        HookController::fireHookBatch($playerConnectHooks, $player);
    }

    static function tmPlayerLeave($arguments)
    {
        $playerData       = json_decode($arguments[0]);
        $playerLeaveHooks = HookController::getHooks('PlayerLeave');

        $player = Player::find($playerData->login);

        HookController::fireHookBatch($playerLeaveHooks, $player);
    }

    /**
     * Convert cp array to comma separated string
     * @param array $cps
     * @return string
     */
    private static function cpArrayToString(array $cps)
    {
        return implode(',', $cps);
    }
}