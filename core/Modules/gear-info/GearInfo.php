<?php

namespace esc\Modules;


use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Models\Player;

class GearInfo
{
    public function __construct()
    {
        ChatCommand::add('/gear', [self::class, 'show'], 'Enable gear up/down indicator');
    }

    public static function show(Player $player)
    {
        Template::show($player, 'gear-info.meter');
    }
}