<?php


namespace EvoSC\Modules\HideScript;


use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class HideScript extends Module implements ModuleInterface
{
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }

    public static function sendScript(Player $player)
    {
        Template::show($player, 'HideScript.script');
    }
}