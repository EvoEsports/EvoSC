<?php


namespace EvoSC\Modules\TeamInfo;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class TeamInfo extends Module implements ModuleInterface
{
    public static function start(string $mode, bool $isBoot = false)
    {
        if (ModeController::teams()) {
            Hook::add('PlayerConnect', [self::class, 'showWidget']);

            if (!$isBoot) {
                $emblems = [
                    Server::getTeamInfo(1)->emblemUrl,
                    Server::getTeamInfo(2)->emblemUrl,
                ];

                Template::showAll('TeamInfo.widget', compact('emblems'));
            }
        }
    }

    public static function showWidget(Player $player)
    {
        $emblems = [
            Server::getTeamInfo(1)->emblemUrl,
            Server::getTeamInfo(2)->emblemUrl,
        ];

        Template::show($player, 'TeamInfo.widget', compact('emblems'));
    }
}