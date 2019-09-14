<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\CountdownController;
use esc\Controllers\MapController;
use esc\Models\Player;

class AddedTimeInfo
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showWidget']);
        Hook::add('AddedTimeChanged', [self::class, 'addedTimeChanged']);
        Hook::add('EndMatch', [self::class, 'resetAddedTimeInfo']);
        Hook::add('MatchSettingsLoaded', [self::class, 'resetAddedTimeInfo']);

        ManiaLinkEvent::add('time.vote', [self::class, 'voteTime'], 'time');
        ManiaLinkEvent::add('time.add', [self::class, 'addTime'], 'time');
    }

    public static function addedTimeChanged($addedSeconds)
    {
        $addedTime = round($addedSeconds / 60, 1);
        Template::showAll('added-time-info.update', compact('addedTime'));
    }

    public static function resetAddedTimeInfo()
    {
        self::addedTimeChanged(0);
    }

    public static function showWidget(Player $player)
    {
        $addedTime = round(CountdownController::getAddedSeconds() / 60, 1);
        Template::show($player, 'added-time-info.update', compact('addedTime'));

        $timeLimitInMinutes = CountdownController::getOriginalTimeLimit() / 60;

        $buttons = [
            round($timeLimitInMinutes / 4, 1),
            round($timeLimitInMinutes / 2, 1),
            round($timeLimitInMinutes, 1),
            round($timeLimitInMinutes * 2, 1),
        ];

        Template::show($player, 'added-time-info.widget', compact('buttons'));
    }

    public static function voteTime(Player $player, $time)
    {
        Votes::askMoreTime($player, floatval($time));
    }

    public static function addTime(Player $player, $time)
    {
        CountdownController::addTime(floatval($time) * 60, $player);
    }
}