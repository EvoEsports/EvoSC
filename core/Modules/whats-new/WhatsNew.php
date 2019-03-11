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
            'New chat commands overview, type /help',
            'Click yes/no on widget to vote',
            '--------------------',
            'Change key-binds, see "Keyboard setup" on the right.'
        ];

        Template::show($player, 'whats-new.window', compact('changes'));
    }
}