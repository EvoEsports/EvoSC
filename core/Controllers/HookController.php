<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Interfaces\ControllerInterface;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class HookController
 *
 * @package EvoSC\Controllers
 */
class HookController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static Collection $hooks;

    /**
     * Initialize HookController
     */
    public static function init()
    {
        self::$hooks = collect();
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
    }

    /**
     * Get all hooks, or all by name
     *
     * @param string|null $eventName
     *
     * @return Collection|null
     */
    static function getHooks(string $eventName = null): ?Collection
    {
        if ($eventName) {
            return self::$hooks->get($eventName);
        }

        return self::$hooks;
    }

    /**
     * @param Hook $hook
     */
    static function removeHook(Hook $hook)
    {
        self::$hooks->put($hook->getEvent(), self::$hooks->get($hook->getEvent())->reject(function (Hook $hookCompare) use ($hook) {
            return $hookCompare === $hook;
        }));
    }

    /**
     * Add a new hook
     *
     * @param string $event
     * @param callable $callback
     * @param bool $runOnce
     * @param int $priority
     *
     * @throws Exception
     */
    public static function add(string $event, callable $callback, bool $runOnce = false, int $priority = 0)
    {
        $hooks = self::$hooks;
        $hook = new Hook($event, $callback, $runOnce, $priority);

        if ($hooks) {
            if (!$hooks->has($event)) {
                $hooks->put($event, collect());
            }

            $hookGroup = $hooks->get($event)->push($hook)->sortByDesc(function (Hook $hook) {
                return $hook->getPriority();
            })->values();

            $hooks->put($event, $hookGroup);

            if (isDebug()) {
                if (gettype($callback) == "object") {
                    Log::write("Added $event (Closure)");
                } else {
                    Log::write("Added " . $callback[0] . "::" . $callback[1]);
                }
            }
        }
    }
}