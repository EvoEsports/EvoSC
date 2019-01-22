<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

class AutoAfk
{

    public function __construct()
    {
    }

    public static function setAfk(Player $player)
    {
        try {
            Server::forceSpectator($player->Login, 3);
        } catch (Exception $e) {
            Log::logAddLine('AutoAfk', $e->getMessage());
        }

        try {
            Server::forceSpectatorTarget($player->Login, "", 2);
        } catch (Exception $e) {
            Log::logAddLine('AutoAfk', $e->getMessage());
        }

        ChatController::message(onlinePlayers(), $player, ' was moved to spectators after ', secondary(config('auto-afk.minutes') . ' minutes'), ' of racing inactivity.');
    }

    public static function forceAfk(Player $player, Player $admin)
    {
        try {
            Server::forceSpectator($player->Login, 3);
        } catch (Exception $e) {
            Log::logAddLine('AutoAfk', $e->getMessage());
        }

        try {
            Server::forceSpectatorTarget($player->Login, "", 2);
        } catch (Exception $e) {
            Log::logAddLine('AutoAfk', $e->getMessage());
        }

        ChatController::message(onlinePlayers(), $player, ' was forced to spectators by ', $admin);
    }
}