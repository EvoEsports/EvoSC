<?php

namespace EvoSC\Classes;


use EvoSC\Controllers\HookController;
use Exception;
use TypeError;

/**
 * Class Hook
 *
 * Hooks are responsible for all server & player-events. Events are transformed to hooks and hooks can also be called internally to pass data to other methods.
 * Register a Hook like Hook::('event_name', callback [, runOnce]). A list of all hooks will follow.
 *
 * callback can either be a method reference like [MyClass::class, 'methodToCall'] or a closure function(){}.
 *
 * Example:
 * Hook::add('PlayerConnect', [PlayerController::class, 'playerConnected']);
 * or
 * Hook::add('BeginMap', function(Map $map){
 *     ... do stuff ...
 * });
 *
 * @package EvoSC\Classes
 */
class Hook
{
    const PRIORITY_HIGHEST = 2;
    const PRIORITY_HIGH = 1;
    const PRIORITY_DEFAULT = 0;
    const PRIORITY_LOW = -1;
    const PRIORITY_LOWEST = -2;

    private bool $runOnce;
    private string $event;
    private $function;
    private int $priority;

    /**
     * Hook constructor.
     *
     * @param string $event
     * @param callable $function
     * @param bool $runOnce
     * @param int $priority
     */
    public function __construct(string $event, callable $function, bool $runOnce = false, int $priority = 0)
    {
        $this->event = $event;
        $this->runOnce = $runOnce;
        $this->priority = $priority;

        if (gettype($function) == "object") {
            $this->function = $function;
        } else {
            if (is_callable($function, false, $callableName)) {
                $this->function = $function;
            } else {
                Log::warning(sprintf('Invalid hook: %s->%s', $function[0], $function[1]), true);
            }
        }
    }

    /**
     * Execute the hook with the given arguments.
     * Warning: Calling a hook with the wrong number of arguments could result in an exception.
     *
     * @param array ...$arguments
     */
    public function execute(...$arguments)
    {
        try {
            if (gettype($this->function) == "object") {
                $func = $this->function;
                $func(...$arguments);
            } else {
                if (is_callable($this->function, false, $callableName)) {
                    call_user_func($this->function, ...$arguments);
                    // Log::write("Execute: " . $this->function[0] . "->" . $this->function[1] . "()", isDebug());
                } else {
                    throw new Exception("Function call invalid, must use: [ClassName, FunctionName] or Closure. " . serialize($this->function));
                }
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            if ($message != "Login unknown.") {
                Log::errorWithCause('Failed to execute hook', $e);
                Log::write(json_encode($this->function), isDebug());
            }
        } catch (TypeError $e) {
            Log::errorWithCause('Failed to execute hook', $e);
        }

        if ($this->runOnce) {
            HookController::removeHook($this);
        }
    }

    /**
     * Get the triggering event associated with the hook.
     *
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * Get the hooks method called on execution.
     *
     * @return callable
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * Use Hook::add.
     * Register a hook.
     *
     * @param string $event
     * @param callable $callback
     * @param bool $runOnce
     * @param int $priority
     */
    public static function add(string $event, callable $callback, bool $runOnce = false, int $priority = 0)
    {
        try {
            HookController::add($event, $callback, $runOnce, $priority);
        } catch (Exception $e) {
            $message = sprintf('Failed to add hook %s: %s', $event, serialize($callback));
            Log::errorWithCause($message, $e);
        }
    }

    /**
     * TODO: Remove a hook
     *
     * @param string $event
     * @param callable $callback
     */
    public static function remove(string $event, callable $callback)
    {
    }

    /**
     * Fire all hooks for the given name and arguments.
     *
     * @param string $hookName
     * @param mixed ...$arguments
     */
    public static function fire(string $hookName, ...$arguments)
    {
        $hooks = HookController::getHooks($hookName);

        if (!$hooks) {
            return;
        }

        foreach ($hooks as $hook) {
            try {
                $hook->execute(...$arguments);
            } catch (Exception $e) {
                Log::errorWithCause("Hook execution failed", $e);
            }
        }
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }
}
