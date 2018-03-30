<?php

namespace esc\Classes;


use esc\Controllers\HookController;
use esc\Controllers\MapController;
use esc\Models\Player;

class ModescriptCallbacks
{
    static function tmScores($arguments)
    {
//        foreach ($arguments as $playerJson) {
//            $player = json_decode($playerJson);
//        }
    }

    static function tmGiveUp($arguments)
    {
        $playerFinishHooks = HookController::getHooks('PlayerFinish');

        $playerLogin = json_decode($arguments[0])->login;
        $player = Player::find($playerLogin);

        HookController::fireHookBatch($playerFinishHooks, $player, 0, "");
    }

    static function tmWayPoint($arguments)
    {
        $playerCheckpointHooks = HookController::getHooks('PlayerCheckpoint');
        $playerFinishHooks = HookController::getHooks('PlayerFinish');

        $wayPoint = json_decode($arguments[0]);

        $player = Player::find($wayPoint->login);
        $map = MapController::getCurrentMap();

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