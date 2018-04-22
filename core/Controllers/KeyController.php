<?php

namespace esc\Controllers;


class KeyController
{
    private static $binds;

    public static function init()
    {
        self::$binds = collect([]);
    }

    public static function createBind(string $key, string $function)
    {
        $bind = collect([
            'key' => $key,
            'function' => $function
        ]);

        self::$binds->push($bind);
    }

    public static function executeBinds(string $key)
    {
        $binds = self::$binds->where('key', $key);

        var_dump($binds);
    }
}