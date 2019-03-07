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
    public $hidden;

    /**
     * ChatCommand constructor.
     *
     * @param string         $trigger
     * @param string         $command
     * @param array|\Closure $callback
     * @param string         $description
     * @param string|null    $access
     */
    public function __construct(string $trigger, string $command, $callback, string $description = '', string $access = null, bool $hidden = false)
    {
        $this->trigger     = $trigger;
        $this->command     = $command;
        $this->callback    = $callback;
        $this->description = $description;
        $this->access      = $access;
        $this->hidden      = $hidden;
    }

    public function hasAccess(Player $player)
    {
        if ($this->access == null) {
            return true;
        }

        return $player->hasAccess($this->access);
    }

    public static function add(string $command, array $callback, string $description = '-', string $trigger = '/', string $access = null)
    {
        ChatController::addCommand($command, $callback, $description, $trigger, $access);
    }

    public function compile()
    {
        return $this->trigger . $this->command;
    }

    public function run(array $arguments)
    {
        if ($this->callback instanceof \Closure) {
            $callback = $this->callback;
            $callback(...$arguments);

            return;
        }

        Log::logAddLine('ChatCommand', sprintf('Call: %s -> %s(%s)', $this->callback[0], $this->callback[1], implode(', ', $arguments)), isVeryVerbose());
        call_user_func_array($this->callback, $arguments);
    }
}