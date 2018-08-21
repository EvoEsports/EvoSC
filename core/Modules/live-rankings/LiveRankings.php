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
    }

    public static function playerConnect(Player $player)
    {
        Template::show($player, 'live-rankings.widget');
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'live-rankings.widget');
    }
}