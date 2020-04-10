<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Controllers\CountdownController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class AddedTimeInfo extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if($mode == 'TimeAttack.Script.txt'){
            if (!$isBoot) {
                self::showWidget();
            }

            Hook::add('PlayerConnect', [self::class, 'showWidget']);
            Hook::add('AddedTimeChanged', [self::class, 'addedTimeChanged']);
            Hook::add('EndMatch', [self::class, 'resetAddedTimeInfo']);
            Hook::add('MatchSettingsLoaded', [self::class, 'resetAddedTimeInfo']);

            ManiaLinkEvent::add('time.vote', [self::class, 'voteTime']);
            ManiaLinkEvent::add('time.add', [self::class, 'addTime'], 'time');
        }else{
            Template::hideAll('add-time');
        }
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

    public static function showWidget(Player $player = null)
    {
        $addedTime = round(CountdownController::getAddedSeconds() / 60, 1);
        $buttons = config('added-time-info.buttons');

        if($player){
            Template::show($player, 'added-time-info.update', compact('addedTime'), false, 20);
            Template::show($player, 'added-time-info.widget', compact('buttons'));
        }else{
            Template::showAll('added-time-info.update', compact('addedTime'));
            Template::showAll('added-time-info.widget', compact('buttons'));
        }
    }

    public static function voteTime(Player $player, string $time)
    {
        Votes::askMoreTime($player, $time);
    }

    public static function addTime(Player $player, $time)
    {
        CountdownController::addTime(floatval($time) * 60, $player);
    }
}