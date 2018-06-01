<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use Illuminate\Database\Eloquent\Collection;

class HookController
{
    private static $hooks;

    public static function init()
    {
        self::$hooks = new Collection();
    }

    static function getHooks(string $hook = null): ?Collection
    {
        if ($hook) {
            return self::$hooks->filter(function ($value, $key) use ($hook) {
                return $value->getEvent() == $hook;
            });
        }

        return self::$hooks;
    }

    public static function add(string $event, string $staticFunction)
    {
        $hooks = self::getHooks();
        $hook  = new Hook($event, $staticFunction);

        if ($hooks) {
            self::getHooks()->add($hook);
            Log::logAddLine('Hook', "Added $event -> $staticFunction", false);
        }
    }

    static function fireHookBatch($hooks, ...$arguments)
    {
        foreach ($hooks as $hook) {
            $hook->execute(...$arguments);
        }
    }
}