<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class Speedometer
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'show']);
    }

    public static function show(Player $player)
    {
        Template::show($player, 'speedometer.meter');
    }
}