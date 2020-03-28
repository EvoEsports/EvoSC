<?php


namespace esc\Modules;


use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class Scoretable extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'sendScoreTable']);
    }

    public static function sendScoreTable(Player $player)
    {
        $logoUrl = config('scoreboard.logo-url');
        $maxPlayers = Server::getMaxPlayers()['CurrentValue'];
        $pointLimitRounds = Server::getRoundPointsLimit()["CurrentValue"];

        $joinedPlayerInfo = collect([$player])->map(function (Player $player) {
            return [
                'login' => $player->Login,
                'name' => ml_escape($player->NickName),
                'groupId' => $player->group->id
            ];
        })->keyBy('login');

        $playerInfo = onlinePlayers()->map(function (Player $player) {
            return [
                'login' => $player->Login,
                'name' => ml_escape($player->NickName),
                'groupId' => $player->group->id
            ];
        })->keyBy('login');

        GroupManager::sendGroupsInformation($player);
        Template::showAll('scoretable.update', ['players' => $joinedPlayerInfo]);
        Template::show($player, 'scoretable.update', ['players' => $playerInfo]);
        Template::show($player, 'scoretable.scoreboard', compact('logoUrl', 'maxPlayers', 'pointLimitRounds'));
    }
}