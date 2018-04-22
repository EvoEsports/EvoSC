<?php

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class Clock
{
    public function __construct()
    {
        Template::add('clock', File::get(__DIR__ . '/Templates/clock.latte.xml'));

        Hook::add('PlayerConnect', 'Clock::displayClock');

        foreach (onlinePlayers() as $player) {
            self::displayClock($player);
        }
    }

    public static function displayClock(Player $player)
    {
        $clock = config('ui.clock');

        Template::show($player, 'clock', compact('clock'));
    }
}