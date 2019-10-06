<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Player;

/**
 * Class BansController
 *
 * Ban/unban players.
 *
 * @package esc\Controllers
 */
class BansController implements ControllerInterface
{
    /**
     * Called on boot
     *
     * @return mixed|void
     */
    public static function init()
    {
        AccessRight::createIfMissing('player_ban', 'Ban and unban players.');
    }

    /**
     * Ban a player
     *
     * @param  Player  $toBan  The player to be banned
     * @param  Player  $admin  The admin who is banning
     * @param  string  $reason  The reason
     */
    public static function ban(Player $toBan, Player $admin, string $reason = '')
    {
        Server::banAndBlackList($toBan->Login, $reason, true);
        warningMessage($admin, ' banned ', $toBan, ', reason: ', secondary($reason))->sendAll();
        $toBan->update(['banned' => 1]);
    }

    /**
     * Unban a player
     *
     * @param  Player  $toUnban  The player to be unbanned
     * @param  Player  $admin  The admin who is unbanning
     */
    public static function unban(Player $toUnban, Player $admin)
    {
        Server::unBan($toUnban->Login);
        infoMessage($admin, ' unbanned ', $toUnban)->sendAll();
        $toUnban->update(['banned' => 0]);
    }

    public static function banPlayerEvent(Player $player, $targetLogin, $reason)
    {
        self::ban(player($targetLogin), $player, $reason);
    }

    /**
     * @param  string  $mode
     * @param  bool  $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        ManiaLinkEvent::add('ban', [self::class, 'banPlayerEvent'], 'player_ban');
    }
}