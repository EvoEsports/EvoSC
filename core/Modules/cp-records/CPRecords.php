<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class CPRecords
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [CPRecords::class, 'playerConnect']);
    }

    public static function playerConnect(Player $player)
    {
        Template::show($player, 'cp-records.widget');
    }
}