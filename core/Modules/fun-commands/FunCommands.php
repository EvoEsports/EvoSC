<?php

namespace esc\Modules;


use esc\Controllers\ChatController;
use esc\Models\Player;

class FunCommands
{
    public function __construct()
    {
        ChatController::addCommand('gg', function(Player $player){
            ChatController::playerChat($player, '$oGood Game');
        }, 'Say Good Game.', '/');

        ChatController::addCommand('gga', function(Player $player){
            ChatController::playerChat($player, '$oGood Game All');
        }, 'Say Good Game All.', '/');
    }
}