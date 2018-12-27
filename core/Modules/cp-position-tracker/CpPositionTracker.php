<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class CpPositionTracker
{
    public function __construct()
    {
        if (config('cp-pos-tracker.enabled')) {
            Hook::add('PlayerConnect', [self::class, 'showManialink']);
        }

        KeyController::createBind('X', [self::class, 'showManialink']);
    }

    public static function showManialink(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'cp-position-tracker.manialink');
    }
}