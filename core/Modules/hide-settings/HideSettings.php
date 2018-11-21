<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class HideSettings
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'sendHideScriptSettings']);

        ManiaLinkEvent::add('hide.settings', [self::class, 'showSettings']);
        ManiaLinkEvent::add('hide.save', [self::class, 'saveSettings']);

        QuickButtons::addButton('', 'UI Hiding Config', 'hide.settings');

        // KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::showSettings($player);
    }

    public static function showSettings(Player $player)
    {
        $speed = $player->setting('hide_speed') ?? 1.0;

        Template::show($player, 'hide-settings.manialink', compact('speed'));
    }

    public static function saveSettings(Player $player, $speed)
    {
        $player->setSetting('hide_speed', $speed);
        self::sendHideScriptSettings($player);
    }

    public static function sendHideScriptSettings(Player $player)
    {
        $speed = $player->setting('hide_speed') ?? 1.0;
        Template::show($player, 'hide-settings.update', compact('speed'));
    }
}