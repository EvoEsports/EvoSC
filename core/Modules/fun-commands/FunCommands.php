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
        ChatController::addCommand('afk', function (Player $player) {
            ChatController::playerChat($player, '$oAway from keyboard.');
            Server::forceSpectator($player->Login, 3);
        }, 'Go AFK.', '/');

        ChatController::addCommand('afk', function (Player $player) {
            ChatController::playerChat($player, '$oAway from keyboard.');
            Server::forceSpectator($player->Login, 3);
        }, 'Go AFK.', '');

        ChatController::addCommand('gg', function (Player $player) {
            ChatController::playerChat($player, '$oGood Game');
        }, 'Say Good Game.', '/');

        ChatController::addCommand('gga', function (Player $player) {
            ChatController::playerChat($player, '$oGood Game All');
        }, 'Say Good Game All.', '/');

        ChatController::addCommand('bootme', function (Player $player) {
            infoMessage($player, ' boots back to the real world!')->sendAll();
            Server::kick($player->Login, 'cya');
            Hook::fire('PlayerDisconnect', $player);
        }, 'Boot yourself back to the real world.', '/');
    }
}