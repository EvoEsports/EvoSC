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
        $this->event = $event;
        $this->function = $function;
        $this->name = $name;
    }

    /**
     * @param array ...$arguments
     */
    public function execute(...$arguments)
    {
        try{
            call_user_func_array($this->function, $arguments); //TODO: deprecated switch to call_user_func()
            Log::logAddLine('Hook', "Execute: $this->function", true);
        }catch(\Exception $e){
            Log::logAddLine('Hook ERROR', "Execution of $this->function failed", true);
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
}