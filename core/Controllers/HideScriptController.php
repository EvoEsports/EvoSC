<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Models\Player;

class HideScriptController
{
    public static function init()
    {
        ChatController::addCommand('hide', 'HideScriptController::showConfig', 'Configure UI hiding. Set speed/enabled.');

        ManiaLinkEvent::add('hsc.toggle', 'HideScriptController::toggle');
        ManiaLinkEvent::add('hsc.set', 'HideScriptController::set');

        Hook::add('PlayerConnect', 'HideScriptController::sendHideScriptSettings');
    }

    public static function toggle(Player $player, $toggle)
    {
        $speed = $player->setting('ui_hide_speed') ?? 1.0;

        if ($speed > 0.0) {
            self::setHideSpeed($player, 0.0);
        } else {
            self::setHideSpeed($player, 500.0);
        }

        self::showConfig($player);
    }

    public static function set(Player $player, $cmd = null, $speed)
    {
        self::setHideSpeed($player, $speed);
    }

    public static function setHideSpeed(Player $player, $hideSpeed = 0.0)
    {
        $player->setSetting('ui_hide_speed', $hideSpeed / 3.8);

        if ($hideSpeed > 0) {
            ChatController::message($player, '_info', 'UI hides now at ', secondary($hideSpeed . ' km/h'));
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
        Template::show($player, 'hide-config', [
            'id' => 'ESC:ui-hide-config',
            'title' => 'UI hiding config',
            'width' => 60,
            'height' => 21,
            'showAnimation' => true,
            'speed' => $speed
        ]);
    }
}