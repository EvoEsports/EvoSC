<?php

namespace esc\Classes;


use esc\Controllers\HookController;

class Hook
{
    private $runOnce;
    private $event;
    private $function;

    /**
     * Hook constructor.
     *
     * @param string $event
     * @param        $function
     * @param bool   $runOnce
     *
     * @throws \Exception
     */
    public function __construct(string $event, $function, bool $runOnce = false)
    {
        $this->event   = $event;
        $this->runOnce = $runOnce;


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
                    Log::logAddLine('Hook', "Execute: " . $this->function[0] . "->" . $this->function[1] . "()", false);
                } else {
                    throw new \Exception("Function call invalid, must use: [ClassName, FunctionName] or Closure");
                }
            }
        } catch (\Exception $e) {
            Log::logAddLine('Hook', "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString(), isVerbose());
            Log::logAddLine('DEBUG', json_encode($this->function));
        } catch (\TypeError $e) {
            Log::logAddLine('Hook', "TypeError: " . $e->getMessage() . "\n" . $e->getTraceAsString(), isVerbose());
        }

        if ($this->runOnce) {
            HookController::removeHook($this);
        }
    }

    /**
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    public function getFunction()
    {
        return $this->function;
    }

    /**
     * @param string         $event
     * @param \Closure|array $callback
     * @param bool           $runOnce
     */
    public static function add(string $event, $callback, bool $runOnce = false)
    {
        HookController::add($event, $callback, $runOnce);
    }

    /**
     * Fire all registered hooks
     *
     * @param string $hookName
     * @param mixed  ...$arguments
     */
    public static function fire(string $hookName, ...$arguments)
    {
        $hooks = HookController::getHooks($hookName);

        if ($hooks->isEmpty()) {
            return;
        }

        foreach ($hooks as $hook) {
            try {
                $hook->execute(...$arguments);
            } catch (\Exception $e) {
                Log::logAddLine('Hook:' . $hook->event, $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }
    }
}