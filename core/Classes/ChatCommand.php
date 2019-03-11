<?php

namespace esc\Classes;


use esc\Models\Player;
use Illuminate\Support\Collection;

class ChatCommand
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $commands;

    public $command;
    public $callback;
    public $description;
    public $access;
    public $hidden;

    /**
     * ChatCommand constructor.
     *
     * @param string      $command
     * @param             $callback
     * @param string      $description
     * @param string|null $access
     * @param bool        $hidden
     */
    public function __construct(string $command, $callback, string $description = '', string $access = null, bool $hidden = false)
    {
        $this->command     = $command;
        $this->callback    = $callback;
        $this->description = $description;
        $this->access      = $access;
        $this->hidden      = $hidden;
    }

    public static function add(string $command, $callback, string $description = '-', string $access = null, bool $hidden = false): ChatCommand
    {
        if (!self::$commands) {
            self::$commands = collect();
        }

        $chatCommand = new ChatCommand($command, $callback, $description, $access, $hidden);
        self::$commands->put($command, $chatCommand);

        return $chatCommand;
    }

    public function addAlias(string $alias): ChatCommand
    {
        $description = sprintf('%s (Alias for %s)', $this->description, $this->command);
        ChatCommand::add($alias, $this->callback, $description, $this->access, $this->hidden);

        return $this;
    }

    public static function has(string $command): bool
    {
        return self::$commands->has($command);
    }

    public static function get(string $command): ChatCommand
    {
        return self::$commands->get($command);
    }

    public static function getCommands(): Collection
    {
        return self::$commands;
    }

    public function execute(Player $player, string $text)
    {
        if ($this->access && !$player->hasAccess($this->access)) {
            warningMessage('Sorry, you are not allowed to do that.')->send($player);

            return;
        }

        //treat "this is a string" as single argument
        if (preg_match_all('/\"(.+?)\"/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                //Replace all spaces in quotes to ;:;
                $new  = str_replace(' ', ';:;', $match);
                $text = str_replace("\"$match\"", $new, $text);
            }
        }

        //Split input string in arguments
        $arguments = explode(' ', $text);

        foreach ($arguments as $key => $argument) {
            //Change ;:; back to spaces
            $arguments[$key] = str_replace(';:;', ' ', $argument);
        }

        //Set calling player as first argument
        array_unshift($arguments, $player);

        if ($this->callback instanceof \Closure) {
            $callback = $this->callback;
            $callback(...$arguments);

            return;
        }

        Log::logAddLine('ChatCommand', sprintf('Call: %s -> %s(%s)', $this->callback[0], $this->callback[1], implode(', ', $arguments)), isVeryVerbose());
        call_user_func_array($this->callback, $arguments);
    }
}