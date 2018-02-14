<?php

namespace esc\classes;


class ChatCommand
{
    public $trigger;
    public $command;
    public $callback;

    public function __construct(string $trigger, string $command, string $callback)
    {
        $this->trigger = $trigger;
        $this->command = $command;
        $this->callback = $callback;
    }
}