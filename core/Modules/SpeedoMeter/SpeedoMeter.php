<?php

namespace EvoSC\Modules\SpeedoMeter;


use EvoSC\Classes\ChatCommand;
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
        if (isManiaPlanet()) {
            Hook::add('PlayerConnect', [self::class, 'show']);
        } else {
            ChatCommand::add('/speed', [self::class, 'cmdShowSpeedo'], 'Show speed on HUD.');
        }

        ManiaLinkEvent::add('speedo.save', [self::class, 'saveSettings']);
        ManiaLinkEvent::add('speedo.reset', [self::class, 'resetSettings']);
    }

    public static function cmdShowSpeedo(Player $player, $cmd)
    {
        self::show($player);
    }

    public static function show(Player $player)
    {
        $settings = $player->setting('speedo');

        Template::show($player, 'SpeedoMeter.meter' . (isTrackmania() ? '_2020' : ''), compact('settings'));
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
