<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\CountdownController;
use esc\Controllers\MapController;
use esc\Controllers\PlanetsController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class Pay2Play implements ModuleInterface
{
    private static $priceAddTime;
    private static $priceSkip;

    public function __construct()
    {
        self::$priceAddTime = config('pay2play.addtime.cost') ?? 500;
        self::$priceSkip = config('pay2play.skip.cost') ?? 5000;

        Hook::add('PlayerConnect', [self::class, 'showWidget']);

        if (config('pay2play.addtime.enabled')) {
            ManiaLinkEvent::add('pay2play.addtime', [self::class, 'addTime']);
        }
        if (config('pay2play.skip.enabled')) {
            ManiaLinkEvent::add('pay2play.skip', [self::class, 'skip']);
        }
    }

    public static function showWidget(Player $player)
    {
        if (config('pay2play.addtime.enabled')) {
            $value = round(CountdownController::getOriginalTimeLimit() / 60);
            Template::show($player, 'pay2play.add-time', compact('value'));
        }

        if (config('pay2play.skip.enabled')) {
            Template::show($player, 'pay2play.skip-map');
        }
    }

    public static function addTime(Player $player)
    {
        if (CountdownController::getAddedSeconds() + CountdownController::getOriginalTimeLimit() >= config('pay2play.addtime.time-limit-in-seconds')) {
            warningMessage('Maximum playtime for this round reached.')->send($player);

            return;
        }

        PlanetsController::createBill($player, self::$priceAddTime,
            'Pay '.self::$priceAddTime.' planets to add more time?', [self::class, 'addTimePaySuccess']);
    }

    public static function addTimePaySuccess(Player $player, int $amount)
    {
        infoMessage($player, ' paid ', $amount, ' to add more time')->sendAll();
        CountdownController::addTime(CountdownController::getOriginalTimeLimit(), $player);
    }

    public static function skip(Player $player)
    {
        PlanetsController::createBill($player, self::$priceSkip, 'Pay '.self::$priceSkip.' planets to skip map?',
            [self::class, 'skipPaySuccess']);
    }

    public static function skipPaySuccess(Player $player, int $amount)
    {
        infoMessage($player, ' paid ', $amount, ' to skip map.')->sendAll();
        MapController::skip($player);
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}