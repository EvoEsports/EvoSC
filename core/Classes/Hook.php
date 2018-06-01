<?php

namespace esc\Classes;


use esc\Controllers\HookController;

class Hook
{
    private $name;
    private $event;
    private $function;

    /**
     * Hook constructor.
     * @param string $event
     * @param string $function
     * @param string|null $name
     */
    public function __construct(string $event, string $function, string $name = null)
    {
        $this->event    = $event;
        $this->function = $function;
        $this->name     = $name;
    }

    /**
     * @param array ...$arguments
     */
    public function execute(...$arguments)
    {
        try {
            $className = explode('::', $this->function)[0];
            $function  = explode('::', $this->function)[1];

            $class = classes()->where('class', $className)->first();

            if ($class) {
                call_user_func_array("$class->namespace::$function", $arguments);
            } else {
                call_user_func_array($this->function, $arguments);
            }

            Log::logAddLine('Hook', "Execute: $this->function", false);
        } catch (\Exception $e) {
            Log::logAddLine('Hook ERROR', "Execution of $this->function() failed: " . $e->getMessage(), true);
            Log::logAddLine('Stack trace', $e->getTraceAsString(), false);
        }
    }

    /**
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * @param string $event
     * @param string $function
     */
    public static function add(string $event, string $function)
    {
        HookController::add($event, $function);
    }

    /**
     * Fire all registered hooks
     * @param string $hookName
     * @param mixed ...$arguments
     */
    public static function fire(string $hookName, ...$arguments)
    {
        $hooks = HookController::getHooks($hookName);

        if ($hooks->isEmpty()) {
            return;
        }

        foreach ($hooks as $hook) {
            $hook->execute(...$arguments);
        }
    }
}