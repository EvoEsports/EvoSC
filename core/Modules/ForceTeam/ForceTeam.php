<?php


namespace EvoSC\Modules\ForceTeam;


use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class ForceTeam extends Module implements ModuleInterface
{
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }

    public static function showWindow(Player $player)
    {
        Template::show($player, 'ForceTeam.window');
    }
}