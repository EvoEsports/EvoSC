<?php

namespace EvoSC\Modules\SpeedoMeter;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class SpeedoMeter extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        global $__ManiaPlanet;

        if(!$__ManiaPlanet){
            return;
        }

        Hook::add('PlayerConnect', [self::class, 'show']);

        ManiaLinkEvent::add('speedo.save', [self::class, 'saveSettings']);
        ManiaLinkEvent::add('speedo.reset', [self::class, 'resetSettings']);
    }

    public static function show(Player $player)
    {
        $settings = $player->setting('speedo');

        Template::show($player, 'SpeedoMeter.meter', compact('settings'));
    }

    public static function saveSettings(Player $player, ...$settingsJson)
    {
        $player->setSetting('speedo', implode(',', $settingsJson));
    }

    public static function resetSettings(Player $player)
    {
        $player->settings()->where('name', 'speedo')->delete();
    }
}
