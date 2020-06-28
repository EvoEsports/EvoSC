<?php


namespace EvoSC\Modules\ScoreTable;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\GroupManager\GroupManager;

class ScoreTable extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        global $__ManiaPlanet;
        if(!$__ManiaPlanet){
            return;
        }

        Hook::add('PlayerConnect', [self::class, 'sendScoreTable']);
    }

    public static function sendScoreTable(Player $player)
    {
        $logoUrl = config('scoretable.logo-url');
        $maxPlayers = Server::getMaxPlayers()['CurrentValue'];

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
        Template::showAll('ScoreTable.update', ['players' => $joinedPlayerInfo], 20);
        Template::show($player, 'ScoreTable.update', ['players' => $playerInfo], false, 20);
        Template::show($player, 'ScoreTable.scoreboard', compact('logoUrl', 'maxPlayers'));
    }
}