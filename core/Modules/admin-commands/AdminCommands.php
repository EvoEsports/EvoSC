<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Player;

class AdminCommands
{
    public static $pwEnabled;

    public function __construct()
    {
        Template::add('acp', File::get(__DIR__ . '/Templates/acp.latte.xml'));

        ManiaLinkEvent::add('ac.replay', 'AdminCommands::forceReplayAtEnd');
        ManiaLinkEvent::add('ac.skip', 'AdminCommands::forceSkipMap');
        ManiaLinkEvent::add('ac.stopvote', 'AdminCommands::stopVote');
        ManiaLinkEvent::add('ac.approvevote', 'AdminCommands::approveVote');
        ManiaLinkEvent::add('ac.lockserver', 'AdminCommands::toggleLockServer');

        ChatCommand::add('setpw', 'AdminCommands::setPw', 'Set server and spec password', '//', 'ban');

        Hook::add('BeginMap', 'AdminCommands::showAdminControlPanel');
        Hook::add('EndMatch', 'AdminCommands::hideAdminControlPanel');
        Hook::add('PlayerConnect', 'AdminCommands::showAdminControlPanel');
    }

    public static function setPw(Player $player, $cmd, $pw)
    {
        if (!$player->isMasteradmin()) {
            return;
        }

        self::toggleLockServer($player, $pw);
    }

    private static function announcePasswordChange(Player $player, string $pw = null)
    {
        if (!$pw) {
            ChatController::messageAll($player, ' removed the server password');
        } else {
            foreach (onlinePlayers() as $ply) {
                if ($ply->isAdmin()) {
                    ChatController::message($ply, $player, ' set a new server password: ', $pw);
                } else {
                    ChatController::message($ply, $player, ' locked the server with a password');
                }
            }
        }
    }

    public static function toggleLockServer(Player $player, string $pw = null)
    {
        if (!$player->isMasteradmin()) {
            return;
        }

        $currentPw = Server::getServerPassword();

        if ($pw) {
            Server::setServerPassword($pw);
            Server::setServerPasswordForSpectator($pw);
            self::announcePasswordChange($player, $pw);
            self::$pwEnabled = true;
        } else {
            if ($currentPw == '') {
                $pw = config('server.pw');
                if ($pw) {
                    //only set password if one is defined in config
                    Server::setServerPassword($pw);
                    Server::setServerPasswordForSpectator($pw);
                    self::announcePasswordChange($player, $pw);
                    self::$pwEnabled = true;
                }
            } else {
                Server::setServerPassword('');
                Server::setServerPasswordForSpectator('');
                self::announcePasswordChange($player);
                self::$pwEnabled = false;
            }
        }


        self::showAdminControlPanel();
    }

    public static function stopVote(Player $player)
    {
        \esc\Classes\Vote::stopVote($player);
    }

    public static function approveVote(Player $player)
    {
        \esc\Classes\Vote::approveVote($player);
    }

    public static function showAdminControlPanel(...$vars)
    {
        $admins = onlinePlayers()->filter(function (Player $ply) {
            return $ply->isAdmin();
        });

        $pwEnabled = self::$pwEnabled;

        foreach ($admins as $player) {
            Template::show($player, 'acp', compact('pwEnabled'));
        }
    }

    public static function hideAdminControlPanel(...$vars)
    {
        $admins = onlinePlayers()->filter(function (Player $ply) {
            return $ply->isAdmin();
        });

        foreach ($admins as $player) {
            Template::hide($player, 'acp');
        }
    }

    public static function forceReplayAtEnd(Player $callee)
    {
        if (!$callee->isAdmin()) {
            ChatController::message($callee, 'Access denied');
        }

        MapController::forceReplay($callee);
    }

    public static function forceSkipMap(Player $callee)
    {
        if (!$callee->isAdmin()) {
            ChatController::message($callee, 'Access denied');
        }

        MapController::goToNextMap();

        ChatController::messageAll($callee->group, ' ', $callee, ' skips map');
    }
}