<?php


namespace EvoSC\Modules\Symbols;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class Symbols extends Module implements ModuleInterface
{
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('/symbols', [self::class, 'showSymbolsWindow'], 'View symbols for ingame usage.');
    }

    public static function showSymbolsWindow(Player $player)
    {
        Template::show($player, 'Symbols.window');
    }
}