<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Interfaces\ControllerInterface;
use Illuminate\Support\Collection;

class HookController implements ControllerInterface
{
    /**
     * @var \Illuminate\Support\Collection
     */
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
     *
     * @param string|null $hook
     *
     * @return Collection|null
     */
    static function getHooks(string $hook = null): ?Collection
    {
        if ($hook) {
            return self::$hooks->filter(function (Hook $value, $key) use ($hook) {
                return $value->getEvent() == $hook;
            });
        }

        return self::$hooks;
    }

    static function removeHook(Hook $hook)
    {
        self::$hooks = self::$hooks->reject(function (Hook $hookCompare) use ($hook) {
            return $hookCompare === $hook;
        });
    }

    /**
     * Add a hook
     *
     * @param string         $event
     * @param \Closure|array $callback
     * @param bool           $runOnce
     */
    public static function add(string $event, $callback, bool $runOnce = false)
    {
        $hooks = self::getHooks();
        $hook  = new Hook($event, $callback, $runOnce);

        if ($hooks) {
            self::getHooks()->push($hook);
            if (gettype($callback) == "object") {
                Log::logAddLine('Hook', "Added $event (Closure)", isVeryVerbose());
            }else{
                Log::logAddLine('Hook', "Added " . $callback[0] . " -> " . $callback[1], isVeryVerbose());
            }
        }
    }
}