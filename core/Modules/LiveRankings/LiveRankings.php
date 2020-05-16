<?php

namespace EvoSC\Modules\LiveRankings;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

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