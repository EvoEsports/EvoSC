<?php

namespace EvoSC\Modules\ServerHopper;


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
     * @var Collection
     */
    private static Collection $connections;

    /**
     * @var Collection
     */
    private static Collection $serverData;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$connections = collect();
        self::$serverData = collect();
        self::connectToToServers();

        Hook::add('PlayerConnect', [self::class, 'showWidget']);

        ManiaLinkEvent::add('server_hopper_join', [self::class, 'mleShowJoinWindow']);

        Timer::create('refresh_server_list', [self::class, 'sendUpdatedServerInformation'], '1m', true);
    }

    /**
     * @throws \Maniaplanet\DedicatedServer\InvalidArgumentException
     */
    public static function connectToToServers()
    {
        foreach (config('server-hopper.servers') as $server) {
            $id = $server->rpc->host . ':' . $server->rpc->port;

            if (self::$connections->has($id)) {
                continue;
            }

            try {
                $connection = Connection::factory($server->rpc->host, $server->rpc->port, 500, $server->rpc->login, $server->rpc->pw);
                self::$connections->put($id, $connection);
                self::updateServerInfo($connection);
            } catch (\Exception $e) {
                Log::errorWithCause('Failed to connect to server ' . $server->rpc->host . ':' . $server->rpc->host, $e);
            }
        }
    }

    /**
     * @param Connection $connection
     * @throws \Maniaplanet\DedicatedServer\InvalidArgumentException
     */
    public static function updateServerInfo(Connection $connection)
    {
        $systemInfo = $connection->getSystemInfo();
        $id = $systemInfo->publishedIp . ':' . $systemInfo->port;

        if (self::$serverData->has($id)) {
            $data = self::$serverData->get($id);
        } else {
            $data = (object)[
                'login'         => $connection->getMainServerPlayerInfo()->login,
                'ip'            => $systemInfo->publishedIp,
                'port'          => $systemInfo->port,
                'player_counts' => array_fill(0, 61, 0)
            ];
        }

        $data->name = $connection->getServerName();
        $data->players = count($connection->getPlayerList());
        $data->max = $connection->getMaxPlayers()['CurrentValue'];
        $data->pw = $connection->getServerPassword() != (isManiaPlanet() ? false : 'No password');
        $data->map = $connection->getCurrentMapInfo()->name;
        $data->player_counts = array_slice($data->player_counts, 1);

        if (isManiaPlanet()) {
            $data->title = $connection->getSystemInfo()->titleId;
        }

        array_push($data->player_counts, $data->players);

        self::$serverData->put($id, $data);
    }

    /**
     * @param Player|null $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     * @throws Exception
     */
    public static function sendUpdatedServerInformation(Player $player = null)
    {
        foreach (self::$connections as $connection) {
            self::updateServerInfo($connection);
        }

        $serversJson = self::$serverData
            ->sortByDesc('players')
            ->values()
            ->toJson();

        if ($player != null) {
            Template::show($player, 'ServerHopper.update', compact('serversJson'), false, 5);
        } else {
            Template::showAll('ServerHopper.update', compact('serversJson'));
        }
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showWidget(Player $player)
    {
        self::sendUpdatedServerInformation($player);
        Template::show($player, 'ServerHopper.widget');
    }

    /**
     * @param Player $player
     * @param string $serverLogin
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function mleShowJoinWindow(Player $player, string $serverLogin)
    {
        Template::show($player, 'ServerHopper.join', [
            'server' => self::$serverData->where('login', $serverLogin)->first()
        ]);
    }
}
