<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Models\Player;
use Illuminate\Support\Collection;

class SpectatorController
{
    private static $specTargets;

    public static function init()
    {
        self::$specTargets = collect([]);

        Hook::add('PlayerInfoChanged', 'esc\Controllers\SpectatorController::playerInfoChanged');
    }

    public static function playerInfoChanged(Collection $players)
    {
        foreach ($players as $player) {
            if (isset($player->spectator_status->currentTargetId) && $player->spectator_status->currentTargetId > 0 && $player->spectator_status->currentTargetId < 255) {
                $target = PlayerController::getPlayerByServerId($player->spectator_status->currentTargetId);

                if ($target) {
                    self::setSpecTarget($player, $target);
                }
            } else {
                self::clearSpecTarget($player);
            }
        }
    }

    private static function clearSpecTarget(Player $speccing)
    {
        self::$specTargets->put($speccing, null);
    }

    private static function setSpecTarget(Player $speccing, Player $target)
    {
        self::$specTargets->put($speccing->Login, $target->Login);
        self::displaySpectatorsWidget($target);
    }

    private static function displaySpectatorsWidget(Player $target)
    {
//        $speccedBy = self::$specTargets->filter(function ($targetLogin) use ($target) {
//            return $targetLogin == $target->Login;
//        });
//
//        $speccingPlayers = onlinePlayers()->whereIn('Login', $speccedBy);
//
//        ChatController::message($target, '_info', 'You are being specced by: ', $speccingPlayers->pluck('NickName')->implode(', '));
    }
}