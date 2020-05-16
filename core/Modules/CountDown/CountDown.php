<?php

namespace EvoSC\Modules\CountDown;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class CountDown extends Module implements ModuleInterface
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showCountdown']);
    }

    public static function showCountdown(Player $player)
    {
        Template::show($player, 'countdown.widget');
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}