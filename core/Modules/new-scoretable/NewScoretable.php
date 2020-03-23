<?php


namespace esc\Modules;


use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class NewScoretable extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }

    public static function sendScoreTable(Player $player){
        Template::show($player, 'new-scoretable.scoreboard');
    }
}