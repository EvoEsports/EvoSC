<?php

namespace esc\Classes;


use esc\Controllers\ChatController;
use esc\Models\Player;

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

        return in_array($player->Group, $this->access);
    }

    public static function add(string $command, string $callback, string $description = '-', string $trigger = '/', array $access = null)
    {
        ChatController::addCommand($command, $callback, $description, $trigger, $access);
    }
}