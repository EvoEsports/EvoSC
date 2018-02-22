<?php

namespace esc\controllers;


use esc\classes\Log;
use Maniaplanet\DedicatedServer\Connection;
use Maniaplanet\DedicatedServer\Structures\Map;
use Maniaplanet\DedicatedServer\Xmlrpc\GameModeException;

class ServerController
{
    private static $rpc;

    public static function initialize($host, $port, $timeout, $login, $password)
    {
        self::$rpc = Connection::factory($host, $port, $timeout, $login, $password);
        self::$rpc->enableCallbacks();

        self::call('SendHideManialinkPage');
    }

    public static function getRpc(): Connection
    {
        return self::$rpc;
    }

    public static function executeCallbacks()
    {
        return self::getRpc()->executeCallbacks();
    }

    public static function call(string $rpc_func, $args = null)
    {
        self::getRpc()->execute($rpc_func, $args);
    }


    public static function forceEndRound()
    {
        try {
            return self::getRpc()->forceEndRound();
        } catch (GameModeException $e) {
            Log::error("Not in Rounds or Laps mode.");
        }
    }

    public static function getNextMapInfo(): Map
    {
        return self::getRpc()->getNextMapInfo();
    }

    public static function getCurrentMapInfo(): Map
    {
        return self::getRpc()->getCurrentMapInfo();
    }

    public static function getMapInfo(string $fileName): Map
    {
        return self::getRpc()->getMapInfo($fileName);
    }
}