<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\Hook;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;
use RuntimeException;
use stdClass;

class RoyalController implements ControllerInterface
{
    private static Collection $trackers;

    public static function init()
    {
        self::$trackers = collect();
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        Hook::add('BeginMatch', function () {
            self::$trackers = collect();
        });
    }

    /**
     * @param Player $player
     * @param stdClass $wayPointData
     */
    public static function playerWayPoint(Player $player, stdClass $wayPointData)
    {
        $blockId = $wayPointData->blockid;
        $tracker = self::$trackers->get($player->id);

        if (in_array($blockId, $tracker->blockIds)) {
            throw new RuntimeException("Player finished same section twice in a row.");
        }

        array_push($tracker->blockIds, $blockId);
        $sectionsFinished = count($tracker->blockIds);
        $time = $wayPointData->time - $tracker->serverStartTime;
        $tracker->totalTime += $time;
        $tracker->totalTimeSection += $time;

        Hook::fire('PlayerFinishSection', $player, $tracker->totalTimeSection, $wayPointData->curlapcheckpoints, $sectionsFinished);

        if ($sectionsFinished == 5) {
            Hook::fire('PlayerFinish', $player, $tracker->totalTime, implode(',', $wayPointData->curlapcheckpoints));

            $tracker->serverStartTime = $wayPointData->time;
            $tracker->totalTime = 0;
            $tracker->blockIds = [];
        }

        $tracker->totalTimeSection = 0;
        self::$trackers->put($player->id, $tracker);
    }

    /**
     * @param Player $player
     * @param stdClass $data
     */
    public static function playerStartLine(Player $player, stdClass $data)
    {
        $tracker = self::$trackers->get($player->id);

        if (is_null($tracker)) {
            self::$trackers->put($player->id, (object)[
                'serverStartTime'  => $data->time,
                'totalTime'        => 0,
                'totalTimeSection' => 0,
                'blockIds'         => []
            ]);
        } else {
            $tracker->serverStartTime = $data->time;
            self::$trackers->put($player->id, $tracker);
        }
    }

    /**
     * @param Player $player
     * @param $data
     */
    public static function playerGiveUp(Player $player, $data)
    {
        $penalty = 1500;
        $tracker = self::$trackers->get($player->id);
        $time = $data->time - $tracker->serverStartTime + $penalty;
        $tracker->totalTime += $time;
        $tracker->totalTimeSection += $time;

        self::$trackers->put($player->id, $tracker);
    }
}