<?php

namespace esc\Modules;


use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class WhatsNew
{
    public function __construct()
    {
        KeyController::createBind('X', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();

        $changes = [
            'Customizable speedometer',
            'Customizable roundtime',
            'Add map vote (/add <mx_id>)',
        ];

        Template::show($player, 'whats-new.window', compact('changes'));
    }
}