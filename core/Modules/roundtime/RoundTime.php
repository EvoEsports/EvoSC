<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class RoundTime
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [RoundTime::class, 'show']);
    }

    public static function show(Player $player)
    {
        Template::show($player, 'roundtime.meter');
    }
}