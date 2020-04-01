<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class LiveRankings extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        switch ($mode) {
            default:
            case 'TimeAttack.Script.txt':
                Hook::add('PlayerConnect', [self::class, 'playerConnect']);
                break;
        }
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'live-rankings.widget');
    }
}