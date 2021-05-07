<?php

namespace EvoSC\Modules\ServerHopper;


use EvoSC\Classes\Cache;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
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
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (count(config('server-hopper.servers'))) {
            try {
                self::sendUpdatedServerInformations();
            } catch (Exception $e) {
                Log::errorWithCause('Failed to send updated server information', $e);
                Log::error('Stopping module ' . self::class);
                return;
            }

            Hook::add('PlayerConnect', [self::class, 'showWidget']);

            ManiaLinkEvent::add('server_hopper_join', [self::class, 'mleShowJoinWindow']);

            Timer::create('refresh_server_list', [self::class, 'sendUpdatedServerInformations'], '1m', true);
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
     * @throws Exception
     */
    public static function sendUpdatedServerInformations(Player $player = null)
    {
        $serversJson = self::getServersData()->values()->toJson();

        if ($player != null) {
            Template::show($player, 'ServerHopper.update', compact('serversJson'), false, 5);
        } else {
            Template::showAll('ServerHopper.update', compact('serversJson'));
        }
    }

    /**
     * @return mixed|string
     * @throws Exception
     */
    private static function getServersData(): Collection
    {
        if (Cache::has('server_hopper')) {
            return collect(Cache::get('server_hopper'));
        }

        $serversJson = collect(config('server-hopper.servers'))->map(function ($server) {
            try {
                $connection = Connection::factory($server->rpc->host, $server->rpc->port, 500, $server->rpc->login, $server->rpc->pw);
                $systemInfo = $connection->getSystemInfo();

                return [
                    'login' => $server->login,
                    'name' => $connection->getServerName(),
                    'description' => $connection->getServerComment(),
                    'players' => count($connection->getPlayerList()),
                    'max' => $connection->getMaxPlayers()['CurrentValue'],
                    'title' => $connection->getVersion()->titleId,
                    'pw' => isManiaPlanet() ? $connection->getServerPassword() != false : false,
                    'ip' => $systemInfo->publishedIp,
                    'port' => $systemInfo->port
                ];
            } catch (Exception $e) {
                Log::errorWithCause('Failed to get server data', $e);
                return null;
            }
        })
            ->filter()
            ->sortByDesc('players');

        Cache::put('server_hopper', $serversJson, now()->seconds(55));

        return $serversJson;
    }

    /**
     * @param Player $player
     * @param string $serverLogin
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     * @throws Exception
     */
    public static function mleShowJoinWindow(Player $player, string $serverLogin)
    {
        $server = self::getServersData()->where('login', $serverLogin)->first();

        Template::show($player, 'ServerHopper.join', ['server' => $server]);
    }
}
