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
        AccessRight::createIfNonExistent('player_ban', 'Ban and unban players.');

        ManiaLinkEvent::add('ban', [self::class, 'banPlayerEvent'], 'player_ban');
    }

    /**
     * Ban a player
     *
     * @param \esc\Models\Player $player The player to be banned
     * @param \esc\Models\Player $admin  The admin who is banning
     * @param string             $reason The reason
     */
    public static function ban(Player $player, Player $admin, string $reason = '')
    {
        Server::banAndBlackList($player->Login, $reason, true);
        warningMessage($admin, ' banned ', $player, ', reason: ', secondary($reason))->sendAll();
        $player->update(['banned' => 1]);
    }

    /**
     * Unban a player
     *
     * @param \esc\Models\Player $player The player to be unbanned
     * @param \esc\Models\Player $admin  The admin who is unbanning
     */
    public static function unban(Player $player, Player $admin)
    {
        Server::unBan($player->Login);
        infoMessage($admin, ' unbanned ', $player)->sendAll();
        $player->update(['banned' => 0]);
    }
}