<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Models\Player;

class RoundTime
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'show']);

        ManiaLinkEvent::add('roundtime.save', [self::class, 'saveSettings']);
        ManiaLinkEvent::add('roundtime.reset', [self::class, 'resetSettings']);
    }

    public static function show(Player $player)
    {
        $settings = $player->setting('speedo');

        Template::show($player, 'roundtime.meter', compact('settings'));
    }

    public static function saveSettings(Player $player, ...$settingsJson)
    {
        $player->setSetting('roundtime', implode(',', $settingsJson));
    }

    public static function resetSettings(Player $player)
    {
        $player->settings()->where('name', 'roundtime')->delete();
    }
}