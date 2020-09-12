<?php

namespace EvoSC\Modules\ServerHopper;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
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
        self::$servers = collect(config('server-hopper.servers'));

        if (count(self::$servers)) {
            self::sendUpdatedServerInformations();

            Hook::add('PlayerConnect', [self::class, 'showWidget']);

            Timer::create('refresh_server_list', [self::class, 'updateServerInformation'], '1m', true);
        }
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showWidget(Player $player)
    {
        self::sendUpdatedServerInformations($player);
        Template::show($player, 'ServerHopper.widget');
    }

    /**
     * @param Player|null $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendUpdatedServerInformations(Player $player = null)
    {
        $serversJson = collect(config('server-hopper.servers'))->map(function ($server) {
            try {
                $connection = Connection::factory($server->rpc->host, $server->rpc->port, 500, $server->rpc->login, $server->rpc->pw);
                $systemInfo = $connection->getSystemInfo();

                return [
                    'login' => $server->login,
                    'name' => $connection->getServerName(),
                    'players' => count($connection->getPlayerList()),
                    'max' => $connection->getMaxPlayers()['CurrentValue'],
                    'title' => $connection->getVersion()->titleId,
                    'pw' => isManiaPlanet() ? $connection->getServerPassword() != false : false,
                    'ip' => $systemInfo->publishedIp,
                    'port' => $systemInfo->port,
                ];
            } catch (Exception $e) {
                return null;
            }
        })
            ->filter()
            ->sortByDesc('players')
            ->values()
            ->toJson();

        if ($player != null) {
            Template::show($player, 'ServerHopper.update', compact('serversJson'), false, 5);
        } else {
            Template::showAll('ServerHopper.update', compact('serversJson'));
        }
    }
}