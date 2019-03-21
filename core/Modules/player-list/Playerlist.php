<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class Playerlist
{
    public function __construct()
    {
        ChatCommand::add('/players', [Playerlist::class, 'show'], 'Show the userlist');

        ManiaLinkEvent::add('players', [self::class, 'show']);

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'PlayerList', 'players');
        }
    }

    public static function show(Player $player)
    {
        $players = onlinePlayers();
        Template::show($player, 'player-list.window', compact('players'));
    }
}