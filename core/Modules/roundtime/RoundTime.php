<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class RoundTime extends Module implements ModuleInterface
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'show']);

        ManiaLinkEvent::add('roundtime.save', [self::class, 'saveSettings']);
        ManiaLinkEvent::add('roundtime.reset', [self::class, 'resetSettings']);
    }

    public static function show(Player $player)
    {
        $settings = $player->setting('roundtime');

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

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}