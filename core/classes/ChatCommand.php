<?php

namespace esc\classes;


class ChatCommand
{
    public $trigger;
    public $command;
    public $callback;
    public $description;

    public function __construct(string $trigger, string $command, string $callback, string $description = '')
    {
        $this->trigger = $trigger;
        $this->command = $command;
        $this->callback = $callback;
        $this->description = $description;
    }

    public function getHelp(): string
    {
        $out = '-> ';
        $out .= str_pad($this->trigger . $this->command, 20, ' ', STR_PAD_RIGHT);
        $out .= $this->description;
        return $out;
    }
}