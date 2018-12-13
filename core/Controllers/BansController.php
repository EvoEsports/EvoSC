<?php

namespace esc\Controllers;


use Carbon\Carbon;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Models\Ban;
use esc\Models\Player;

class BansController
{
    public static function init()
    {
        ChatController::addCommand('ban', [PlayerController::class, 'banPlayer'], 'Ban player by nickname', '//', 'ban');
        ManiaLinkEvent::add('ban', [self::class, 'banPlayerEvent'], 'ban');
    }

    public static function ban(Player $player, Player $admin, int $length = 0, string $reason = null)
    {
        $now = Carbon::now();

        Ban::create([
            'player_id' => $player->id,
            'banned_by' => $admin->id,
            'dob'       => $now->toDateTimeString(),
            'length'    => $length,
            'reason'    => $reason,
        ]);

        Server::ban($player->Login, $reason);
        Server::blackList($player->Login);

        if ($length > 0) {
            $diff = $now->addSeconds($length)->diffForHumans();
            if ($reason) {
                ChatController::message(onlinePlayers(), '_warning', $admin, ' banned ', $player, ' for ', secondary($diff), ', Reason: ', secondary($reason));
            } else {
                ChatController::message(onlinePlayers(), '_warning', $admin, ' banned ', $player, ' for ', secondary($diff));
            }
        } else {
            if ($reason) {
                ChatController::message(onlinePlayers(), '_warning', $admin, ' banned ', $player, ' permanently, Reason: ', secondary($reason));
            } else {
                ChatController::message(onlinePlayers(), '_warning', $admin, ' banned ', $player, ' permanently');
            }
        }
    }

    public static function unban(Player $player, Player $admin)
    {
        $ban = Ban::wherePlayerId($player->id)->first();

        if ($ban) {
            $ban->remove();
            ChatController::message(onlinePlayers(), $admin, ' unbanned ', $player);
        }
    }

    public static function banPlayerEvent(Player $player, $login, $length, $reason = "")
    {
        try {
            $toBeKicked = Player::find($login);
        } catch (\Exception $e) {
            $toBeKicked = $login;
        }

        $kicked = Server::rpc()->kick($login, $reason);

        if (!$kicked) {
            return;
        }

        if (strlen($reason) > 0) {
            ChatController::message(onlinePlayers(), '_info', $player, ' kicked ', secondary($toBeKicked), secondary(' Reason: ' . $reason));
        } else {
            ChatController::message(onlinePlayers(), '_info', $player, ' kicked ', secondary($toBeKicked));
        }
    }

    /**
     * Ban a player
     *
     * @param Player $player
     * @param        $cmd
     * @param        $nick
     * @param mixed  ...$message
     */
    public static function banPlayer(Player $player, $cmd, $nick, ...$message)
    {
        $playerToBeBanned = PlayerController::findPlayerByName($player, $nick);

        if (!$playerToBeBanned) {
            return;
        }

        try {
            $reason = implode(" ", $message);
            Server::ban($playerToBeBanned->Login, $reason);
            Server::blackList($playerToBeBanned->Login);
            ChatController::message(onlinePlayers(), $player, ' banned ', $playerToBeBanned, '. Reason: ',
                secondary($reason));
        } catch (\InvalidArgumentException $e) {
            Log::logAddLine('BansController', 'Failed to ban player: ' . $e->getMessage(), true);
            Log::logAddLine('BansController', '' . $e->getTraceAsString(), false);
        }
    }
}