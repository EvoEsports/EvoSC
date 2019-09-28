<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class LiveRankings implements ModuleInterface
{
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'live-rankings.widget');
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        switch ($mode) {
            case 'TimeAttack.Script.txt':
                if (!$isBoot) {
                    Template::showAll('live-rankings.widget');
                }
                Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        }
    }
}