<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class Speedometer
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'show']);

        ManiaLinkEvent::add('speedo.save', [self::class, 'saveSettings']);
        ManiaLinkEvent::add('speedo.reset', [self::class, 'resetSettings']);

        KeyBinds::add('reload_music_client', 'Reload speedo.', [self::class, 'reload'], 'F2', 'ms');
    }

    public static function show(Player $player)
    {
        $settings = $player->setting('speedo');

        Template::show($player, 'speedometer.meter', compact('settings'));
    }

    public static function saveSettings(Player $player, ...$settingsJson)
    {
        $player->setSetting('speedo', implode(',', $settingsJson));
    }

    public static function resetSettings(Player $player)
    {
        $player->settings()->where('name', 'speedo')->delete();
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::show($player);
    }
}