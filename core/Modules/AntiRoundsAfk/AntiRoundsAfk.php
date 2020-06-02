<?php


namespace EvoSC\Modules\AntiRoundsAfk;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class AntiRoundsAfk extends Module implements ModuleInterface
{

    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'show']);
    }

    public static function show(Player $player)
    {
        $timeout = 15000;

        Template::show($player, 'AntiRoundsAfk.script', compact('timeout'));
    }

    public static function mlePutMeToSpec(Player $player)
    {
        Server::forceSpectator($player->Login, 3);
        infoMessage(secondary($player), ' was moved to spectators due to inactivity.')->sendAll();
    }
}