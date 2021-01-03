<?php


namespace EvoSC\Modules\SpectatorInfo;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class SpectatorInfo extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet()) {
            return;
        }

        Server::triggerModeScriptEvent('Common.UIModules.SetProperties', [json_encode([
            'uimodules' => [
                [
                    'id' => 'Race_SpectatorBase_Name',
                    'visible' => true,
                    'visible_update' => true,
                    'position' => [0, -200],
                    'position_update' => true,
                ]
            ]
        ])]);

        Hook::add('PlayerConnect', [self::class, 'showSpecInfo']);

        Template::showAll('SpectatorInfo.widget');
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showSpecInfo(Player $player)
    {
        Template::show($player, 'SpectatorInfo.widget');
    }
}