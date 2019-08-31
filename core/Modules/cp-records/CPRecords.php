<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class CPRecords implements ModuleInterface
{
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'cp-records.widget');
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function start(string $mode)
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
    }
}