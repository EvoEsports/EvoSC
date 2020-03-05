<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\Module;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class ResetModule extends Module implements ModuleInterface
{
    public function __construct()
    {
        ChatCommand::add('/reset', [self::class, 'reset'], 'Reset the UI in case it broke.');
        ChatCommand::add('//resetall', [self::class, 'resetAll'], 'Reset the UI in case it broke.', 'ma');
    }

    public static function reset(Player $player)
    {
        Hook::fire('PlayerConnect', $player);
    }

    public static function resetAll()
    {
        onlinePlayers()->each(function (Player $player) {
            Hook::fire('PlayerConnect', $player);
        });
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}