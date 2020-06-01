<?php


namespace EvoSC\Modules\RaceRanking;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
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
        if ($mode == 'Rounds.Script.txt') {
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
        $points = collect(self::getPointsRepartition())->toJson();

        if (is_null($player)) {
            Template::showAll('RaceRanking.widget', compact('points'));
        } else {
            Template::show($player, 'RaceRanking.widget', compact('points'));
        }
    }

    /**
     * @return array
     */
    private static function getPointsRepartition(): array
    {
        $points = Server::getModeScriptSettings()['S_PointsRepartition'];

        if ($points) {
            $parts = explode(',', $points);
            return array_map(function ($point) {
                return intval($point);
            }, $parts);
        }

        return [10, 6, 4, 3, 2, 1];
    }
}