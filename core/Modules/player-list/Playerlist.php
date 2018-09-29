<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class Playerlist
{
    public function __construct()
    {
        ChatCommand::add('players', [Playerlist::class, 'show'], 'Show the userlist');

        ManiaLinkEvent::add('players', [self::class, 'show']);

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'PlayerList', 'players');
        }

        // KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::show($player);
    }

    public static function show(Player $player)
    {
        //If player_id > 0 then player is online, or get players that disconnected but finished
        $players = Player::where('player_id', '>', 0)->orWhere('Score', '>', 0)->get();

        Template::show($player, 'player-list.window', compact('players'));
    }
}