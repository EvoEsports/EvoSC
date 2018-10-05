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
    public function __construct(string $event, array $function, string $name = null)
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
            if(is_callable($this->function, false,$callableName)){
                call_user_func($this->function, ...$arguments);
                Log::logAddLine('Hook', "Execute: " . $this->function[0] . " " . $this->function[1], false);
            }else{
                throw new \Exception("Function call invalid, must use: [ClassName, ClassFunctionName]");
            }
        } catch (\Exception $e) {
            Log::logAddLine('Hook', "Exception: " . $e->getMessage(), isVerbose());
            Log::logAddLine('Stack trace', $e->getTraceAsString(), isVerbose());
        } catch (\TypeError $e) {
            Log::logAddLine('Hook', "TypeError: " . $e->getMessage(), isVerbose());
            Log::logAddLine('Stack trace', $e->getTraceAsString(), isVerbose());
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
     * @param $callback
     */
    public static function add(string $event, array $callback)
    {
        HookController::add($event, $callback);
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