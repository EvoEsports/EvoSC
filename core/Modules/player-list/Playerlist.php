<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class Playerlist
{
    public function __construct()
    {
        ChatCommand::add('/players', [Playerlist::class, 'show'], 'Show the player-list.');

        ManiaLinkEvent::add('players', [self::class, 'show']);
        ManiaLinkEvent::add('mute', [self::class, 'mute'], 'player_mute');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'PlayerList', 'players');
        }
    }

    public static function show(Player $player)
    {
        Template::show($player, 'player-list.window');
    }

    public static function mute(Player $player, $targetLogin)
    {
        $target = player($targetLogin);

        if (ChatController::isPlayerMuted($target)) {
            ChatController::unmute($player, $target->Login);
        } else {
            ChatController::mute($player, $target);
        }
    }
}