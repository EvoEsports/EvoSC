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
        ManiaLinkEvent::add('sb.load_missing_logins', [self::class, 'mleLoadMissingLogin']);

        Hook::add('PlayerConnect', [self::class, 'sendScoreTable']);
    }

    public static function sendScoreTable(Player $player)
    {
        $logoUrl = config('scoreboard.logo-url');
        $maxPlayers = Server::getMaxPlayers()['CurrentValue'];
        $pointLimitRounds = Server::getRoundPointsLimit()["CurrentValue"];

        GroupManager::sendGroupsInformation($player);
        Template::show($player, 'scoretable.scoreboard', compact('logoUrl', 'maxPlayers', 'pointLimitRounds'));
    }

    public static function mleLoadMissingLogin(Player $player, string $login)
    {
        $player_ = DB::table('players')
            ->select(['NickName as name', 'Login as login', 'Group as groupId'])
            ->where('Login', '=', $login)
            ->first();

        Template::showAll('scoretable.update', ['player' => $player_]);
    }
}