<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class HideScriptController
{
    public static function init()
    {
        ChatController::addCommand('hidespeed', 'HideScriptController::setHideSpeed', 'Set speed at which UI hides, 0 = disable hiding');
        ChatController::addCommand('hide', 'HideScriptController::showConfig', 'Configure UI hiding. Set speed/enabled.');

        Hook::add('PlayerConnect', 'HideScriptController::sendHideScriptSettings');
    }

    public static function setHideSpeed(Player $player, $cmd = null, $hideSpeed = 0)
    {
        if (!$cmd || $hideSpeed === null) {
            return;
        }

        $player->setSetting('ui_hide_speed', $hideSpeed * 1.0);

        if ($hideSpeed > 0) {
            ChatController::message($player, '_info', 'UI hides now at ', secondary($hideSpeed));
        } else {
            ChatController::message($player, '_info', 'UI hiding disabled');
        }

        self::sendHideScriptSettings($player);
    }

    public static function sendHideScriptSettings(Player $player)
    {
        $speed = $player->setting('ui_hide_speed') ?? 1.0;
        Template::show($player, 'hide-settings', compact('speed'));
    }

    public static function showConfig(Player $player, $cmd = null)
    {
        $speed = $player->setting('ui_hide_speed') ?? 1.0;
        Template::show($player, 'hide-config', compact('speed'));
    }
}