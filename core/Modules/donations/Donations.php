<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Controllers\PlanetsController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class Donations extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'show']);

        ManiaLinkEvent::add('donate', [self::class, 'donate']);

        ChatCommand::add('/donate', [self::class, 'donateCmd'], 'Donate planets to the server "/donate <amount>"');
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
    }
}