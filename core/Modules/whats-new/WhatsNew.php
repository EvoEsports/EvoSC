<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class WhatsNew
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showNews']);
    }

    public static function showNews(Player $player)
    {
        $changes = [
            'Customizable speedometer (size/position/label)',
            'Customizable roundtime (size/position/label)',
            'Add map vote (/add <mx_id>)',
            'Change UI hiding speed with button on the right',
            'Skip music',
        ];

        Template::show($player, 'whats-new.window', compact('changes'));
    }
}