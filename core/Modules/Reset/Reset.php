<?php

namespace EvoSC\Modules\Reset;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class Reset extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
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
}