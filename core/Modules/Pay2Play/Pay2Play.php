<?php

namespace EvoSC\Modules\Pay2Play;

use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\CountdownController;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\PlanetsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\Votes\Votes;

class Pay2Play extends Module implements ModuleInterface
{
    private static $priceAddTime;
    private static $priceSkip;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
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
            Template::show($player, 'Pay2Play.add-time', compact('value'));
        }

        if (config('pay2play.skip.enabled')) {
            Template::show($player, 'Pay2Play.skip-map');
        }
    }

    public static function addTime(Player $player)
    {
        $addTimeVoteSuccess = Votes::getAddTimeSuccess();
        if (!is_null($addTimeVoteSuccess) && !$addTimeVoteSuccess) {
            warningMessage('Can not force time when vote resulted in no.')->send($player);
            return;
        }

        if (CountdownController::getAddedSeconds() + CountdownController::getOriginalTimeLimit() >= config('pay2play.addtime.time-limit-in-seconds')) {
            warningMessage('Maximum playtime for this round reached.')->send($player);

            return;
        }

        PlanetsController::createBill($player, self::$priceAddTime,
            'Pay ' . self::$priceAddTime . ' planets to add more time?', [self::class, 'addTimePaySuccess']);
    }

    public static function addTimePaySuccess(Player $player, int $amount)
    {
        infoMessage($player, ' paid ', $amount, ' to add more time')->sendAll();
        CountdownController::addTime(CountdownController::getOriginalTimeLimit(), $player);
    }

    public static function skip(Player $player)
    {
        PlanetsController::createBill($player, self::$priceSkip, 'Pay ' . self::$priceSkip . ' planets to skip map?',
            [self::class, 'skipPaySuccess']);
    }

    public static function skipPaySuccess(Player $player, int $amount)
    {
        infoMessage($player, ' paid ', $amount, ' to skip map.')->sendAll();
        MapController::skip($player);
    }
}