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

    public function execute($arguments = null)
    {
        call_user_func_array($this->function, $arguments);
        Log::hook("Execute: $this->function");
        Log::hook("   ARGS: " . ($arguments ? implode(', ', array_keys($arguments[0])) : ''));
        Log::hook(" VALUES: " . ($arguments ? implode(', ', array_values($arguments[0])) : ''));
    }

    public function getEvent(): string
    {
        return $this->event;
    }
}