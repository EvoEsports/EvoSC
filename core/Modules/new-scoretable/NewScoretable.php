<?php


namespace esc\Modules;


use esc\Classes\Module;
use esc\Classes\Server;
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

    public static function sendScoreTable(Player $player)
    {
        $logoUrl = config('scoreboard.logo-url');
        $maxPlayers = Server::getMaxPlayers()['CurrentValue'];
        $pointLimitRounds = Server::getRoundPointsLimit()["CurrentValue"];
        Template::show($player, 'new-scoretable.scoreboard', compact('logoUrl', 'maxPlayers', 'pointLimitRounds'));
    }
}