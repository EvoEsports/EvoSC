<?php

namespace EvoSC\Modules\LiveRankings;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MatchController;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\PointsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;

class LiveRankings extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('MatchTrackerUpdated', [self::class, 'sendUpdatedValues']);

        if(!$isBoot){
            Template::showAll('LiveRankings.widget', ['originalPointsLimit' => PointsController::getOriginalPointsLimit()]);
        }
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function playerConnect(Player $player)
    {
        self::sendUpdatedValues(MatchController::getTracker());

        $originalPointsLimit = PointsController::getOriginalPointsLimit();
        Template::show($player, 'LiveRankings.widget', compact('originalPointsLimit'));
    }

    /**
     * @param Collection $top
     */
    public static function sendUpdatedValues(Collection $top)
    {
        $showTop = config('live-rankings.show', 14);

        if(ModeController::isTimeAttackType()){
            $top = $top->sortBy('score')->take($showTop)->values()->toJson();
        }else{
            $top = $top->sortByDesc('points')->take($showTop)->values()->toJson();
        }

        Template::showAll('LiveRankings.update', compact('top'));
    }
}