<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

/**
 * Class BansController
 *
 * Ban/unban players.
 *
 * @package EvoSC\Controllers
 */
class BansController implements ControllerInterface
{
    /**
     * @param  string  $mode
     * @param  bool  $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        ManiaLinkEvent::add('ban', [self::class, 'banPlayerEvent'], 'player_ban');
    }

    /**
     * Called on boot
     *
     * @return mixed|void
     */
    public static function init()
    {
        AccessRight::add('player_ban', 'Ban and unban players.');
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
        if ($toBan->group->security_level > $admin->group->security_level) {
            warningMessage('You can not ban players with a higher security-level than yours.')->send($admin);
            infoMessage($admin, ' tried to kick you but was blocked.')->send($toBan);
            return;
        }

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

    public static function banPlayerEvent(Player $player, $targetLogin, $reason = '')
    {
        self::ban(player($targetLogin), $player, $reason);
    }
}