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
    }

    public static function showCountdown(Player $player)
    {
        Template::show($player, 'countdown.widget');
    }
}