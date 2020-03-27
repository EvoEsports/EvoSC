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
        ManiaLinkEvent::add('sb.load_missing_logins', [self::class, 'mleLoadMissingLogins']);

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

    public static function mleLoadMissingLogins(Player $player, ...$logins)
    {
        $players = DB::table('players')
            ->select(['NickName as name', 'Login as login', 'Group as groupId'])
            ->whereIn('Login', $logins)
            ->get()
            ->map(function ($player) {
                $player->name = ml_escape($player->name);
                return $player;
            })
            ->keyBy('login');

        dump($players, $logins);

        Template::show($player, 'scoretable.update', compact('players'));
    }
}