<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class Speedometer implements ModuleInterface
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'show']);

        ManiaLinkEvent::add('speedo.save', [self::class, 'saveSettings']);
        ManiaLinkEvent::add('speedo.reset', [self::class, 'resetSettings']);
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

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}