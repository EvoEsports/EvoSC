<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\PlanetsController;
use esc\Controllers\TemplateController;
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

            /*
            if (config('votes.enabled')) {
                if (Votes::addTimeFailed() && !config('pay2play.addtime.forceable')) {
                    ChatController::message($player,'_info', 'Can not force more playtime after a vote failed.');
                    return;
                }
            }
            */

            if (MapController::getAddedTime() + 10 <= config('pay2play.addtime.time-limit')) {
                PlanetsController::createBill($player, self::$priceAddTime, 'Pay ' . self::$priceAddTime . ' planets to add more time?', [Pay2Play::class, 'addTimePaySuccess']);
            } else {
                ChatController::message($player, '_warning', 'Maximum playtime for this round reached');
            }
        }
    }

    public static function addTimePaySuccess(Player $player, int $amount)
    {
        ChatController::message(onlinePlayers(), '_info', $player, ' paid ', $amount, ' to add more time');
        MapController::addTime(10);
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
        ChatController::message(onlinePlayers(), '_info', $player, ' paid ', $amount, ' to skip map');
        MapController::skip($player);
    }
}