<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\CountdownController;
use esc\Controllers\MapController;
use esc\Controllers\PlanetsController;
use esc\Models\Player;

class Pay2Play
{
    private static $priceAddTime;
    private static $priceSkip;

    public function __construct()
    {
        self::$priceAddTime = config('pay2play.addtime.cost') ?? 500;
        self::$priceSkip    = config('pay2play.skip.cost') ?? 5000;

        Hook::add('PlayerConnect', [self::class, 'showWidget']);

        ManiaLinkEvent::add('addtime', [self::class, 'addTime']);
        ManiaLinkEvent::add('skip', [self::class, 'skip']);
    }

    public static function showWidget(Player $player)
    {
        Template::show($player, 'pay2play.widget');
    }

    public static function addTime(Player $player)
    {
        if (config('pay2play.addtime.enabled')) {
            /* TODO: Block force replay */

            if (CountdownController::getAddedSeconds() + CountdownController::getOriginalTimeLimit() >= config('pay2play.addtime.time-limit')) {
                warningMessage('Maximum playtime for this round reached.')->send($player);

                return;
            }

            PlanetsController::createBill($player, self::$priceAddTime, 'Pay ' . self::$priceAddTime . ' planets to add more time?', [self::class, 'addTimePaySuccess']);
        }
    }

    public static function addTimePaySuccess(Player $player, int $amount)
    {
        infoMessage($player, ' paid ', $amount, ' to add more time')->sendAll();
        CountdownController::addTime(CountdownController::getOriginalTimeLimit(), $player);
        Template::showAll('pay2play.widget');
    }

    public static function skip(Player $player)
    {
        if (config('pay2play.skip.enabled')) {
            PlanetsController::createBill($player, self::$priceSkip, 'Pay ' . self::$priceSkip . ' planets to skip map?', [self::class, 'skipPaySuccess']);
        }
    }

    public static function skipPaySuccess(Player $player, int $amount)
    {
        infoMessage($player, ' paid ', $amount, ' to skip map.')->sendAll();
        MapController::skip($player);
    }
}