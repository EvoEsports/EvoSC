<?php

namespace esc\Classes;


use esc\controllers\HookController;

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
        call_user_func_array($this->function, $arguments);
        Log::hook("Execute: $this->function");
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