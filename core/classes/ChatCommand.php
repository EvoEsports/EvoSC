<?php

namespace esc\classes;


use esc\models\Player;

class ChatCommand
{
    public $trigger;
    public $command;
    public $callback;
    public $description;
    public $access;

    public function __construct(string $trigger, string $command, string $callback, string $description = '', array $access = null)
    {
        $this->trigger = $trigger;
        $this->command = $command;
        $this->callback = $callback;
        $this->description = $description;
        $this->access = $access;
    }

    public function hasAccess(Player $player): bool
    {
        if ($this->access == null) {
            return true;
        }

        return in_array($player->group->Name, $this->access);
    }
}