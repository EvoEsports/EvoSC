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
        // KeyController::createBind('X', [self::class, 'reload']);

        Hook::add('PlayerConnect', [self::class, 'showManialink']);

        ManiaLinkEvent::add('afk', [self::class, 'setAfk']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'auto-afk.manialink');
    }

    public static function showManialink(Player $player)
    {
        Template::show($player, 'auto-afk.manialink');
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
}