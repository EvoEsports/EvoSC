<?php

namespace esc\Modules;


use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class AdminOverlay
{
    public function __construct()
    {
        KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'admin-overlay.overlay');
    }
}