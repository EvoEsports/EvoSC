<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class CpPositionTracker
{
    public function __construct()
    {
        if (config('cp-pos-tracker.enabled')) {
            Hook::add('PlayerConnect', [self::class, 'showManialink']);
        }
    }

    public static function showManialink(Player $player)
    {
        Template::show($player, 'cp-position-tracker.manialink');
    }
}