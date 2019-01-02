<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Interfaces\ControllerInterface;
use Illuminate\Database\Eloquent\Collection;

class HookController implements ControllerInterface
{
    private static $hooks;

    /**
     * Initialize HookController
     */
    public static function init()
    {
        self::$hooks = new Collection();
    }

    /**
     * Get all hooks, or all by name
     * @param string|null $hook
     * @return Collection|null
     */
    static function getHooks(string $hook = null): ?Collection
    {
        if ($hook) {
            return self::$hooks->filter(function ($value, $key) use ($hook) {
                return $value->getEvent() == $hook;
            });
        }

        return self::$hooks;
    }

    /**
     * Add a hook
     * @param string $event
     * @param array $callback
     */
    public static function add(string $event, array $callback)
    {
        $hooks = self::getHooks();
        $hook  = new Hook($event, $callback);

        if ($hooks) {
            self::getHooks()->add($hook);
            Log::logAddLine('Hook', "Added " . $callback[0] . " -> " . $callback[1], false);
        }
    }
}