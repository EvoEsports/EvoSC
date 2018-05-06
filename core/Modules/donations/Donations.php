<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\PlanetsController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class Donations
{
    public function __construct()
    {
        Hook::add('PlayerConnect', 'Donations::show');

        ManiaLinkEvent::add('donate', 'Donations::donate');
    }

    public static function show(Player $player)
    {
        Template::show($player, 'donations.widget');
    }

    public static function donate(Player $player, $amount)
    {
        PlanetsController::createBill($player, $amount, "Donate $amount Planets?", 'Donations::paySuccess');
    }

    public static function paySuccess(Player $player, $amount)
    {
        $player->stats()->increment('Donations', $amount);
        ChatController::messageAll('_info', $player, ' donated ', secondary("$amount Planets"), ' to the server, thank you!');

        Hook::fire('PlayerDonate', $player, $amount);
    }
}