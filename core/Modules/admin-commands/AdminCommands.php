<?php


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Controllers\PlayerController;
use esc\Models\Group;
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

        Hook::add('BeginMap', 'AdminCommands::showAdminControlPanel');
        Hook::add('EndMatch', 'AdminCommands::hideAdminControlPanel');
        Hook::add('PlayerConnect', 'AdminCommands::showAdminControlPanel');
    }

    public static function toggleLockServer(Player $player)
    {
        if (!$player->isMasteradmin()) {
            return;
        }

        $currentPw = Server::getServerPassword();

        if ($currentPw == '') {
            $pw = config('server.pw');
            if ($pw) {
                ChatController::messageAll($player, ' locked the server with a password.');
                Server::setServerPassword($pw);
                self::$pwEnabled = true;
            }
        } else {
            ChatController::messageAll($player, ' removed the server password.');
            Server::setServerPassword('');
            self::$pwEnabled = false;
        }
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

        foreach ($admins as $player) {
            Template::show($player, 'acp');
        }
    }

    public static function hideAdminControlPanel(...$vars)
    {
        $admins = PlayerController::getPlayers()->filter(function (Player $ply) {
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

        ChatController::messageAll($callee, ' skips map');
    }
}