<?php

namespace esc\Controllers;


use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\Player;

class BansController implements ControllerInterface
{
    /**
     * Called on boot
     *
     * @return mixed|void
     */
    public static function init()
    {
        ChatController::addCommand('ban', [PlayerController::class, 'banPlayer'], 'Ban player by nickname', '//', 'ban');

        ManiaLinkEvent::add('ban', [self::class, 'banPlayerEvent'], 'ban');
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
        ChatController::message(onlinePlayers(), '_warning', $admin, ' banned ', $player, ', reason: ', secondary($reason));
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
        ChatController::message(onlinePlayers(), '_warning', $admin, ' unbanned ', $player);
    }

    /**
     * [INTERNAL] Handle ban player mania script action
     *
     * @param \esc\Models\Player $admin
     * @param                    $login
     * @param string             $reason
     */
    public static function banPlayerEvent(Player $admin, $login, $reason = '')
    {
        $toBeBanned = Player::find($login);

        if (!$toBeBanned) {
            return;
        }

        self::ban($toBeBanned, $admin, $reason);
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