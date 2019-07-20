<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\BansController;
use esc\Models\AccessRight;
use esc\Models\Player;

class BanGUI
{
    public function __construct()
    {
        AccessRight::createIfNonExistent('player_ban', 'Ban/unban players.');

        ChatCommand::add('//ban', [self::class, 'cmdBanPlayer'], 'Ban & blacklist player.', 'player_ban');

        ManiaLinkEvent::add('banui.show_bans', [self::class, 'showBansTab']);
        ManiaLinkEvent::add('banui.show_add_ban', [self::class, 'showAddBanTab']);
        ManiaLinkEvent::add('banui.search', [self::class, 'mleSearchPlayerAndShowResults']);
        ManiaLinkEvent::add('banui.ban', [self::class, 'mleBanPlayer'], 'player_ban');
    }

    public static function showBansTab(Player $player)
    {
        $bans = Server::getBlackList(0, 999);
        Template::show($player, 'ban-gui.list', compact('bans'));
    }

    public static function showAddBanTab(Player $player)
    {
        Template::show($player, 'ban-gui.add');
    }

    public static function mleSearchPlayerAndShowResults(Player $player, $search)
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

        Template::show($player, 'ban-gui.add', compact('results', 'search'));
    }

    public static function cmdBanPlayer(Player $player, $cmd, $name = null)
    {
        if ($name) {
            self::mleSearchPlayerAndShowResults($player, $name);
        } else {
            self::showAddBanTab($player);
        }
    }

    public static function mleBanPlayer(Player $player, $login, ...$reasonParts)
    {
        $toBan = Player::find($login);

        if (count($reasonParts) > 0) {
            $reason = implode(' ', $reasonParts);
        } else {
            $reason = '';
        }

        try {
            BansController::ban($toBan, $player, $reason);
        } catch (\Exception $e) {
            warningMessage($e->getMessage())->send($player);
            Log::write('BanGUI', 'Failed to ban & blacklist: ' . $login);
        }
    }
}