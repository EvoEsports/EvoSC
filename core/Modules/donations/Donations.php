<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\PlanetsController;
use esc\Models\Player;

class Donations
{
    public function __construct()
    {
        Hook::add('PlayerConnect', 'Donations::show');

        ManiaLinkEvent::add('donate', 'Donations::donate');

        ChatCommand::add('donate', 'Donations::donateCmd', 'Donate planets to the server "/donate <amount>"');
    }

    public static function show(Player $player)
    {
        Template::show($player, 'donations.widget');
    }

    public static function donateCmd(Player $player, $cmd, $amount)
    {
        self::donate($player, intval($amount));
    }

    public static function donate(Player $player, $amount)
    {
        if ($amount < 1) {
            //Block donations with less then one planet
            ChatController::message($player, '_warning', 'You can not donate less than 1 planet');
            return;
        }

        PlanetsController::createBill($player, $amount, "Donate $amount Planets?", 'Donations::paySuccess');
    }

    public static function paySuccess(Player $player, $amount)
    {
        $player->stats()->increment('Donations', $amount);
        ChatController::messageAll('_info', $player, ' donated ', secondary("$amount Planets"), ' to the server, thank you!');

        Hook::fire('PlayerDonate', $player, $amount);
    }
}