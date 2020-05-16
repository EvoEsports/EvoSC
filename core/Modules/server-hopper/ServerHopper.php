<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;
use Exception;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Connection;

class ServerHopper extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static $servers;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {

        /**
         * TODO: Add pagination
         */

        self::$servers = collect(config('server-hopper.servers'));

        if (count(self::$servers)) {
            self::updateServerInformation();

            Hook::add('PlayerConnect', [self::class, 'showWidget']);

            Timer::create('refresh_server_list', [self::class, 'updateServerInformation'], '1m', true);
        }
    }

    public static function showWidget(Player $player)
    {
        self::sendUpdatedServerInformations($player);
        Template::show($player, 'server-hopper.widget');
    }

    public static function sendUpdatedServerInformations(Player $player = null)
    {
        $serversJson = self::$servers->map(function ($server) {
            if ($server->online) {
                return [
                    'login'   => $server->login,
                    'name'    => $server->name,
                    'players' => $server->players,
                    'max'     => $server->maxPlayers,
                    'title'   => $server->titlePack,
                    'pw'      => $server->hasPassword,
                ];
            }

            return null;
        })->filter()->sortByDesc('players')->values()->toJson();

        if ($player != null) {
            Template::show($player, 'server-hopper.update', compact('serversJson'), false, 20);
        } else {
            Template::showAll('server-hopper.update', compact('serversJson'));
        }
    }

    public static function updateServerInformation()
    {
        self::$servers->transform(function ($server) {
            try {
                $connection          = Connection::factory($server->rpc->host, $server->rpc->port, 500, $server->rpc->login, $server->rpc->pw);
                $server->online      = true;
                $server->name        = $connection->getServerName();
                $server->players     = count($connection->getPlayerList());
                $server->maxPlayers  = $connection->getMaxPlayers()['CurrentValue'];
                $server->titlePack   = $connection->getVersion()->titleId;
                $server->hasPassword = $connection->getServerPassword() != false;
            } catch (Exception $e) {
                $server->online = false;
            }

            return $server;
        });

        self::sendUpdatedServerInformations();
    }
}