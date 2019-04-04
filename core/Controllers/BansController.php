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

        ChatCommand::add('//ban', [self::class, 'banPlayer'], 'Ban player by nickname.', 'player_ban');

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
        $player->update(['banned' => true]);
        warningMessage($admin, ' banned ', $player, ', reason: ', secondary($reason))->sendAll();
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
        $player->update(['banned' => false]);
        infoMessage($admin, ' unbanned ', $player)->sendAll();
    }

    /**
     * [INTERNAL] Handle ban player mania script action
     *
     * @param \esc\Models\Player $admin
     * @param                    $login
     * @param string             $reason
     */
    public static function banPlayerEvent(Player $admin, $cmd, $login, $reason = '')
    {
        self::ban(player($login), $admin, $reason);
    }

    /**
     * [INTERNAL] Handle ban player chat command
     *
     * @param \esc\Models\Player $admin
     * @param                    $cmd
     * @param                    $nick
     * @param mixed              ...$reason
     */
    public static function banPlayer(Player $admin, $cmd, $nick, ...$reason)
    {
        $playerToBeBanned = PlayerController::findPlayerByName($admin, $nick);

        if (!$playerToBeBanned) {
            return;
        }

        self::ban($playerToBeBanned, $admin, implode(' ', $reason));
    }
}