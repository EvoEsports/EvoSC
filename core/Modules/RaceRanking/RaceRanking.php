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
    private static array $tracker;
    private static array $pointsRepartition = [];

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$tracker = [];

        if (ModeController::isRoundsType()) {
            Hook::add('PlayerConnect', [self::class, 'showWidget']);
            Hook::add('PlayerFinish', [self::class, 'playerFinish']);
            Hook::add('Maniaplanet.StartPlayLoop', [self::class, 'clearWidget']);
            Hook::add('EndMatch', [self::class, 'clearWidget']);
            Hook::add('PointsRepartition', [self::class, 'updatePointsRepartition']);

            if(!$isBoot){
                $points = collect(self::$pointsRepartition)->toJson();
                Template::showAll( 'RaceRanking.widget', compact('points'));
            }
        }
    }

    /**
     * @param $pointsRepartition
     */
    public static function updatePointsRepartition($pointsRepartition)
    {
        self::$pointsRepartition = $pointsRepartition;
        $points = collect($pointsRepartition)->toJson();
        Template::showAll('RaceRanking.widget', compact('points'));
    }

    /**
     * @param Player|null $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showWidget(Player $player)
    {
        $points = collect(self::$pointsRepartition)->toJson();
        Template::show($player, 'RaceRanking.widget', compact('points'));
    }

    /**
     * @param Player $player
     * @param int $time
     */
    public static function playerFinish(Player $player, int $time)
    {
        if ($time == 0) {
            return;
        }

        array_push(self::$tracker, (object)[
            'login' => $player->Login,
            'name' => $player->NickName,
            'time' => $time
        ]);

        $data = collect(self::$tracker)
            ->sortBy('time')
            ->toJson();

        Template::showAll('RaceRanking.update', compact('data'));
    }

    /**
     *
     */
    public static function clearWidget()
    {
        self::$tracker = [];
        $data = '[]';

        Template::showAll('RaceRanking.update', compact('data'));
    }
}