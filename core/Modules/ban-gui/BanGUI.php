<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Player;

class BanGUI
{
    public function __construct()
    {
        ChatCommand::add('//ban', [self::class, 'banPlayer'], 'Ban & blacklist player.', 'player_ban');

        ManiaLinkEvent::add('ban.search', [self::class, 'searchPlayerAndShowResults']);
    }

    public static function showAddBanTab(Player $player)
    {
        Template::show($player, 'ban-gui.manialink');
    }

    public static function showBansTab(Player $player)
    {
        $bans = Server::getBlackList(0, 999);
        var_dump($bans);
    }

    public static function searchPlayerAndShowResults(Player $player, $search)
    {
        $results = Player::pluck('NickName', 'Login')->filter(function ($nick, $login) use ($search) {
            if ($login == $search || strpos($login, $search) !== false) {
                return true;
            }

            if (strpos(stripAll($nick), $search) !== false) {
                return true;
            }

            return false;
        });

        Template::show($player, 'ban-gui.manialink', compact('results', 'search'));
    }

    public static function banPlayer(Player $player, $cmd, $name = null)
    {
        if ($name) {
            self::searchPlayerAndShowResults($player, $name);
        } else {
            self::showAddBanTab($player);
        }
    }
}