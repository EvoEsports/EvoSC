<?php

namespace esc\Classes;


use Maniaplanet\DedicatedServer\Connection;
use Maniaplanet\DedicatedServer\Xmlrpc\GameModeException;

class Server
{
    private static $rpc;

    public static function init($host, $port, $timeout, $login, $password)
    {
        self::$rpc = Connection::factory($host, $port, $timeout, $login, $password);
        self::$rpc->enableCallbacks();

        self::call('SendHideManialinkPage');
    }

    private static function getRpc(): Connection
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

    public static function __callStatic($name, $arguments)
    {
        if (method_exists(self::$rpc, $name)) {
//            Log::logAddLine('RPC', "Call $name()", true);
            return call_user_func_array([self::$rpc, $name], $arguments);
        } else {
            Log::error("Calling undefined rpc-method: $name");
        }
    }
}