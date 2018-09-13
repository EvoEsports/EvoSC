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

    public static function rpc(): Connection
    {
        return self::$rpc;
    }

    public static function call(string $rpc_func, $args = null)
    {
        self::rpc()->execute($rpc_func, $args);
    }

    public static function __callStatic($name, $arguments)
    {
        if (method_exists(self::$rpc, $name)) {
            return call_user_func_array([self::$rpc, $name], $arguments);
        } else {
            Log::error("Calling undefined rpc-method: $name");
        }
    }
}