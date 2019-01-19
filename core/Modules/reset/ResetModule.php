<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Models\Player;

class ResetModule
{
    public function __construct()
    {
        ChatCommand::add('reset', [self::class, 'reset'], 'Reset the UI in case it broke.');
    }

    public static function reset(Player $player)
    {
        Hook::fire('PlayerConnect', $player);
    }
}