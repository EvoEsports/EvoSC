<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Controllers\ChatController;
use esc\Controllers\PlayerController;
use esc\Models\Player;

class FunCommands
{
    public function __construct()
    {
        ChatCommand::add('afk', function (Player $player) {
            ChatController::playerChat($player, '$oAway from keyboard.');
            Server::forceSpectator($player->Login, 3);
        }, 'Go AFK.', '/');

        ChatCommand::add('afk', function (Player $player) {
            ChatController::playerChat($player, '$oAway from keyboard.');
            Server::forceSpectator($player->Login, 3);
        }, 'Go AFK.', '');

        ChatCommand::add('gg', function (Player $player) {
            ChatController::playerChat($player, '$oGood Game');
        }, 'Say Good Game.', '/');

        ChatCommand::add('gga', function (Player $player) {
            ChatController::playerChat($player, '$oGood Game All');
        }, 'Say Good Game All.', '/');

        ChatCommand::add('bootme', function (Player $player) {
            infoMessage($player, ' boots back to the real world!')->sendAll();
            Server::kick($player->Login, 'cya');
            Hook::fire('PlayerDisconnect', $player);
        }, 'Boot yourself back to the real world.', '/');
    }
}