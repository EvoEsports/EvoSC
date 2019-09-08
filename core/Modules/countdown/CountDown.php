<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class CountDown
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showCountdown']);
        Hook::add('BeginMap', [self::class, 'showCountdownAll']);
    }

    public static function showCountdownAll()
    {
        Template::showAll('countdown.widget');
    }

    public static function showCountdown(Player $player)
    {
        Template::show($player, 'countdown.widget');
    }
}