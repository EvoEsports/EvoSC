<?php

namespace EvoSC\Modules\AddedTimeInfo;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\CountdownController;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\Votes\Votes;

class AddedTimeInfo extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isTrackmania()) {
            return;
        }

        if (ModeController::isTimeAttackType()) {
            if (!$isBoot) {
                self::showWidget();
            }

            Hook::add('PlayerConnect', [self::class, 'showWidget']);
            Hook::add('AddedTimeChanged', [self::class, 'addedTimeChanged']);
            Hook::add('EndMatch', [self::class, 'resetAddedTimeInfo']);
            Hook::add('MatchSettingsLoaded', [self::class, 'resetAddedTimeInfo']);

            ManiaLinkEvent::add('time.vote', [self::class, 'voteTime']);
            ManiaLinkEvent::add('time.add', [self::class, 'addTime'], 'manipulate_time');
        } else {
            Template::hideAll('add-time');
        }
    }

    public static function addedTimeChanged($addedSeconds)
    {
        $addedTime = round($addedSeconds / 60, 1);
        Template::showAll('AddedTimeInfo.update', compact('addedTime'));
    }

    public static function resetAddedTimeInfo()
    {
        self::addedTimeChanged(0);
    }

    public static function showWidget(Player $player = null)
    {
        $addedTime = round(CountdownController::getAddedSeconds() / 60, 1);
        $buttons = config('added-time-info.buttons');

        if ($player) {
            Template::show($player, 'AddedTimeInfo.update', compact('addedTime'), false, 20);
            Template::show($player, 'AddedTimeInfo.widget', compact('buttons'));
        } else {
            Template::showAll('AddedTimeInfo.update', compact('addedTime'));
            Template::showAll('AddedTimeInfo.widget', compact('buttons'));
        }
    }

    public static function voteTime(Player $player, string $time)
    {
        Votes::cmdAskMoreTime($player, null, $time);
    }

    public static function addTime(Player $player, $time)
    {
        CountdownController::addTime(floatval($time) * 60, $player);
    }
}