<?php

namespace EvoSC\Modules\CountDown;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\CountdownController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class CountDown extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'showCountdown']);
        Hook::add('AddedTimeChanged', [self::class, 'addedTimeChanged']);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showCountdown(Player $player)
    {
        $addedTime = round(CountdownController::getAddedSeconds() / 60, 1);

        Template::show($player, 'CountDown.widget' . (isTrackmania() ? '_2020' : ''));
        Template::show($player, 'CountDown.update-added-time', compact('addedTime'));
    }

    /**
     * @param $addedSeconds
     */
    public static function addedTimeChanged($addedSeconds)
    {
        $addedTime = round($addedSeconds / 60, 1);
        Template::showAll('CountDown.update-added-time', compact('addedTime'));
    }
}