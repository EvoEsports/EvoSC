<?php

namespace EvoSC\Modules\RoundTime;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class RoundTime extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'show']);

        ManiaLinkEvent::add('roundtime.save', [self::class, 'saveSettings']);
        ManiaLinkEvent::add('roundtime.reset', [self::class, 'resetSettings']);
    }

    public static function show(Player $player)
    {
        $settings = $player->setting('roundtime');

        Template::show($player, 'RoundTime.meter', compact('settings'));
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