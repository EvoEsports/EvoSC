<?php

namespace esc\Controllers;


class HideScriptController
{
    public static function init()
    {
        ChatController::addCommand('hidespeed', 'PlayerController::setHideSpeed', 'Set speed at which UI hides, 0 = disable hiding');
    }

    public static function setHideSpeed(Player $player, $cmd = null, $hideSpeed = 0)
    {
        if (!$cmd || $hideSpeed === null) {
            return;
        }

        $player->setSetting('ui->hideSpeed', $hideSpeed);

        if ($hideSpeed == 0) {
            ChatController::message($player, '_info', 'UI hiding disabled');
        } else {
            ChatController::message($player, '_info', 'UI hides now at ', $hideSpeed);
        }
    }
}