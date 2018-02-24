<?php


use esc\classes\File;
use esc\classes\Hook;
use esc\classes\ManiaLinkEvent;
use esc\classes\Template;
use esc\controllers\ChatController;
use esc\controllers\MapController;
use esc\controllers\PlayerController;
use esc\models\Group;
use esc\models\Player;

class AdminCommands
{
    public function __construct()
    {
        Template::add('acp', File::get(__DIR__ . '/Templates/acp.latte.xml'));

        ManiaLinkEvent::add('ac.replay', 'AdminCommands::forceReplayAtEnd');
        ManiaLinkEvent::add('ac.skip', 'AdminCommands::forceSkipMap');
        ManiaLinkEvent::add('ac.stopvote', 'AdminCommands::stopVote');
        ManiaLinkEvent::add('ac.approvevote', 'AdminCommands::approveVote');

        Hook::add('BeginMap', 'AdminCommands::showAdminControlPanel');
        Hook::add('EndMatch', 'AdminCommands::hideAdminControlPanel');
        Hook::add('PlayerConnect', 'AdminCommands::showAdminControlPanel');
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

        ChatController::messageAllNew($callee, ' skips map');
    }
}