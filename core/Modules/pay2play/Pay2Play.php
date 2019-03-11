<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\ChatCommand;
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

        Hook::add('PlayerConnect', [Pay2Play::class, 'showWidget']);

        ManiaLinkEvent::add('addtime', [Pay2Play::class, 'addTime']);
        ManiaLinkEvent::add('skip', [Pay2Play::class, 'skip']);
    }

    public static function showWidget(Player $player)
    {
        Template::show($player, 'pay2play.widget');
    }

    public static function addTime(Player $player)
    {
        if (config('pay2play.addtime.enabled')) {
            /* TODO: Block force replay */

            if (MapController::getAddedTime() + MapController::getTimeLimit() >= config('pay2play.addtime.time-limit')) {
                warningMessage('Maximum playtime for this round reached.')->send($player);

                return;
            }

            PlanetsController::createBill($player, self::$priceAddTime, 'Pay ' . self::$priceAddTime . ' planets to add more time?', [Pay2Play::class, 'addTimePaySuccess']);
        }
    }

    public static function addTimePaySuccess(Player $player, int $amount)
    {
        infoMessage($player, ' paid ', $amount, ' to add more time')->sendAll();
        MapController::addTime(MapController::getTimeLimit());
        onlinePlayers()->each([self::class, 'showWidget']);
    }

    public static function skip(Player $player)
    {
        if (config('pay2play.skip.enabled')) {
            PlanetsController::createBill($player, self::$priceSkip, 'Pay ' . self::$priceSkip . ' planets to skip map?', [Pay2Play::class, 'skipPaySuccess']);
        }
    }

    public static function skipPaySuccess(Player $player, int $amount)
    {
        infoMessage($player, ' paid ', $amount, ' to skip map.')->sendAll();
        MapController::skip($player);
    }
}