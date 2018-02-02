<?php

namespace esc\classes;


class Hook
{
    private $name;
    private $event;
    private $function;

    public function __construct(string $event, string $function, string $name = null)
    {
        $this->event = $event;
        $this->function = $function;
        $this->name = $name;
    }

    public function execute(...$arguments)
    {
        call_user_func_array($this->function, $arguments);
        Log::hook("Execute: $this->function");
//        Log::hook("  +ARGS: " . (is_array($arguments) ? implode(', ', array_keys($arguments)) : $arguments));
//        Log::hook("+VALUES: " . (is_array($arguments) ? implode(', ', array_values($arguments)) : $arguments));
    }

    public function getEvent(): string
    {
        return $this->event;
    }
}