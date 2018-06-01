<?php

namespace esc\Modules;

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class Clock
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [Clock::class, 'displayClock']);
    }

    public static function onConfigReload()
    {
        $clock = config('ui.clock');
        Template::showAll('clock.clock', compact('clock'));
    }

    public static function displayClock(Player $player)
    {
        $clock = config('ui.clock');
        Template::show($player, 'clock.clock', compact('clock'));
    }
}