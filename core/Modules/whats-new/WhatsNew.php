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
            'Change key-binds, see "Keyboard setup" on the right.',
            '----------------------------------------------------',
            'Personal messages, usage: /pm <partial_nickname> message'
        ];

        Template::show($player, 'whats-new.window', compact('changes'));
    }
}