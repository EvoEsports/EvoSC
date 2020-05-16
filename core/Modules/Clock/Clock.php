<?php

namespace EvoSC\Modules\Clock;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class Clock extends Module implements ModuleInterface
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'displayClock']);
        Hook::add('ConfigUpdated', [self::class, 'configUpdated']);
    }

    public static function displayClock(Player $player)
    {
        Template::show($player, 'clock.clock');
    }

    public static function configUpdated($config = null)
    {
        if ($config && isset($config->id) && $config->id == "clock" || $config->id == "colors") {
            onlinePlayers()->each(function (Player $player) use ($config) {
                $clock = $config->data;
                Template::show($player, 'clock.clock', compact('clock'));
            });
        }
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}