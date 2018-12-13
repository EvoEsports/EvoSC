<?php

namespace esc\Controllers;


use Carbon\Carbon;
use esc\Classes\Server;
use esc\Models\Ban;
use esc\Models\Player;

class BansController
{
    public static function banPlayer(Player $player, Player $admin, int $length = 0, string $reason = null)
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
}