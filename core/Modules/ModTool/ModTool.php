<?php


namespace EvoSC\Modules\ModTool;


use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class ModTool extends Module implements ModuleInterface
{
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }

    public static function showWidget(Player $player)
    {
        Template::show($player, 'ModTool.widget');
    }
}