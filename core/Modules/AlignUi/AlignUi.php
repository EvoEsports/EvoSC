<?php

namespace EvoSC\Modules\AlignUi;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class AlignUi extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'sendScript']);
    }

    /**
     * @param Player $player
     * @return void
     */
    public static function sendScript(Player $player)
    {
        Timer::create("align_ui_$player", function () use ($player) {
            Template::show($player, 'AlignUi.script');
        }, '5s');
    }
}