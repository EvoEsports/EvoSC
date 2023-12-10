<?php


namespace EvoSC\Modules\AntiRoundsAfk;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class AntiRoundsAfk extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        global $__ManiaPlanet;

        if (!$__ManiaPlanet) {
            return;
        }

        Hook::add('PlayerConnect', [self::class, 'show']);

        ManiaLinkEvent::add('anti_afk.spec', [self::class, 'mlePutMeToSpec']);
    }

    /**
     * @param Player $player
     * @return void
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function show(Player $player)
    {
        Template::show($player, 'AntiRoundsAfk.script', ['timeout' => 15000]);
    }

    /**
     * @param Player $player
     * @return void
     */
    public static function mlePutMeToSpec(Player $player)
    {
        Server::forceSpectator($player->Login, 3);
        infoMessage(secondary($player), ' was moved to spectators due to inactivity.')->sendAll();
    }
}