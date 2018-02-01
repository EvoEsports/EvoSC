<?php

namespace esc\controllers;


use Maniaplanet\DedicatedServer\Connection;

class RpcController
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
}