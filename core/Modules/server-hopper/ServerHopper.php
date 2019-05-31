<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Models\Player;
use Maniaplanet\DedicatedServer\Connection;

class ServerHopper
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $servers;

    public function __construct()
    {
        /**
         * TODO: Add pagination
         */

        self::$servers = collect(config('server-hopper.servers'));

        if (count(self::$servers)) {
            self::updateServerInformation();

            Hook::add('PlayerConnect', [self::class, 'showWidget']);

            Timer::create('refresh_server_list', [self::class, 'updateServerInformation'], '30s', true);
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
        })->filter()->values()->toJson();

        if ($player != null) {
            Template::show($player, 'server-hopper.update', compact('serversJson'));
        } else {
            Template::showAll('server-hopper.update', compact('serversJson'));
        }
    }

    public static function updateServerInformation()
    {
        self::$servers = self::$servers->map(function ($server) {
            try {
                $connection          = Connection::factory($server->rpc->host, $server->rpc->port, 100, $server->rpc->login, $server->rpc->pw);
                $server->online      = true;
                $server->name        = $connection->getServerName();
                $server->players     = count($connection->getPlayerList());
                $server->maxPlayers  = $connection->getMaxPlayers()['CurrentValue'];
                $server->titlePack   = $connection->getVersion()->titleId;
                $server->hasPassword = $connection->getServerPassword() != false;
            } catch (\Exception $e) {
                $server->online = false;
            }

            return $server;
        });

        self::sendUpdatedServerInformations();
    }
}