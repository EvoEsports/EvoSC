<?php


namespace EvoSC\Modules\RaceRanking;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\PointsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class RaceRanking extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (ModeController::isRounds()) {
            Hook::add('PlayerConnect', [self::class, 'showWidget']);

            if (!$isBoot) {
                self::showWidget();
            }
        }
    }

    /**
     * @param Player|null $player
     */
    public static function showWidget(Player $player = null)
    {
        $points = collect(PointsController::getPointsRepartition())->toJson();

        if (is_null($player)) {
            Template::showAll('RaceRanking.widget', compact('points'));
        } else {
            Template::show($player, 'RaceRanking.widget', compact('points'));
        }
    }
}