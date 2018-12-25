<?php

namespace esc\Modules;


use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class CpPositionTracker
{
    public function __construct()
    {
        KeyController::createBind('X', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'cp-position-tracker.manialink');
    }
}