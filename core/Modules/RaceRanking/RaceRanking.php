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
            Hook::add('Maniaplanet.StartPlayLoop', [self::class, 'startPlayLoop']);

            if (!$isBoot) {
                self::showWidget();
            }
        } else {
            Template::hideAll('race-ranking'); //TODO: clear slot so widgets align properly
        }
    }

    /**
     * @param Player|null $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
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
    public static function startPlayLoop()
    {
        self::$tracker = [];
        $data = '[]';

        Template::showAll('RaceRanking.update', compact('data'));
    }
}