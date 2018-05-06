<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Controllers\ChatController;
use esc\Controllers\HookController;
use esc\Controllers\PlanetsController;
use esc\Models\Player;

class Donations
{
    public function __construct()
    {
        Hook::add('PlayerConnect', 'Donations::show');
    }

    public static function show(Player $player)
    {
        PlanetsController::createBill($player, 100, 'test payment', 'Donations::paySuccess');
    }

    public static function paySuccess(Player $player, $amount)
    {
        $player->stats()->increment('Donations', $amount);
        ChatController::messageAll('_info', $player, ' donated ', secondary("$amount Planets"), ' to the server, thank you!');

        Hook::fire('PlayerDonate', $player, $amount);
    }
}