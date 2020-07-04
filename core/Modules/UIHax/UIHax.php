<?php


namespace EvoSC\Modules\UIHax;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class UIHax extends Module implements ModuleInterface
{
    public static function start(string $mode, bool $isBoot = false)
    {
        if(isManiaPlanet()){
            return;
        }

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
    }

    public static function playerConnect(Player $player)
    {
        Template::show($player, 'UIHax.spacer', ['slot' => 11, 'position' => 'right']);
    }
}