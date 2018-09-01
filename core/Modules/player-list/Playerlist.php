<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Models\Player;

class Playerlist
{
    public function __construct()
    {
        ChatCommand::add('players', [Playerlist::class, 'show'], 'Show the userlist');

        ManiaLinkEvent::add('players', [self::class, 'show']);

        if(config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'PlayerList', 'players');
        }
    }

    public static function show(Player $player)
    {
        $players = Player::where('player_id', '>', 0)->orWhere('Score', '>', 0)->get();

        $playerlist = Template::toString('player-list.players', compact('players'));

        Template::show($player, 'components.modal', [
            'id' => 'player-list-modal',
            'title' => ' Playerlist',
            'width' => 50,
            'height' => $players->count() * 5 + 20,
            'showAnimation' => true,
            'content' => $playerlist
        ]);
    }
}