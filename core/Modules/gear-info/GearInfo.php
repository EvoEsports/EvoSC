<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class GearInfo
{
    public function __construct()
    {
        ChatController::addCommand('gear', [self::class, 'show'], 'Enable gear up/down indicator');
    }

    public static function show(Player $player)
    {
        Template::show($player, 'gear-info.meter');
    }
}