<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class LiveRankings
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [LiveRankings::class, 'playerConnect']);
        // KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::playerConnect($player);
    }

    public static function playerConnect(Player $player)
    {
        Template::show($player, 'live-rankings.widget');
    }
}