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
        Hook::add('PlayerConnect', [Donations::class, 'show']);

        ManiaLinkEvent::add('donate', [Donations::class, 'donate']);

        ChatCommand::add('donate', [Donations::class, 'donateCmd'], 'Donate planets to the server "/donate <amount>"');
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
            warningMessage('You can not donate less than 1 planet.')->send($player);

            return;
        }

        PlanetsController::createBill($player, $amount, "Donate $amount Planets?", [Donations::class, 'paySuccess']);
    }

    public static function paySuccess(Player $player, $amount)
    {
        $player->stats()->increment('Donations', $amount);
        infoMessage($player, ' donated ', secondary("$amount Planets"), ' to the server, thank you!')->sendAll();

        Hook::fire('PlayerDonate', $player, $amount);
    }
}