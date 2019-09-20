<?php


namespace esc\Modules;


use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class Scoreboard implements ModuleInterface
{

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

    public static function sendScoreboard(Player $player)
    {
        $scoreboard = Template::toString('scoreboard.scoreboard');

        Template::show($player, 'scoreboard.bootstrap', compact('scoreboard'));
//        echo Template::toString('scoreboard.bootstrap', compact('scoreboard'));
    }
}