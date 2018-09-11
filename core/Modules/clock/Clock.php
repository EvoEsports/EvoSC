<?php

namespace esc\Modules;

use esc\Classes\Config;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class Clock
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [Clock::class, 'displayClock']);
        Hook::add('ConfigUpdated', [Clock::class, 'configUpdated']);
    }

    public static function displayClock(Player $player)
    {
        $clock = config('clock');
        Template::show($player, 'clock.clock', compact('clock'));
    }

    public static function configUpdated(Config $config = null)
    {
        if ($config && $config->id == "clock" || $config->id == "colors") {
            onlinePlayers()->each(function (Player $player) use ($config) {
                $clock = $config->data;
                Template::show($player, 'clock.clock', compact('clock'));
            });
        }
    }
}