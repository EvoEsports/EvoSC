<?php

namespace EvoSC\Modules\BanGUI;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\BansController;
use EvoSC\Interfaces\ModuleInterface as ModuleInterfaceAlias;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use Exception;

class BanGUI extends Module implements ModuleInterfaceAlias
{
    public function __construct()
    {
        AccessRight::createIfMissing('player_ban', 'Ban/unban players.');

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

    public static function showAddBanTab(Player $player, $targetLogin = '')
    {
        Template::show($player, 'ban-gui.add', compact('targetLogin'));
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
        } catch (Exception $e) {
            warningMessage($e->getMessage())->send($player);
            Log::write('Failed to ban & blacklist: ' . $login);
        }
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}