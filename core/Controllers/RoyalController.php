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

        Hook::fire('PlayerFinishSection', $player, $wayPointData->racetime, $wayPointData->curlapcheckpoints, $sectionsFinished);

        if ($sectionsFinished == 5) {
            $score = $wayPointData->time - $tracker->startServerTime;
            Hook::fire('PlayerFinish', $player, $score, implode(',', $wayPointData->curlapcheckpoints));

            self::$trackers->forget($player->id);
        } else {
            self::$trackers->put($player->id, $tracker);
        }
    }

    /**
     * @param Player $player
     * @param stdClass $data
     */
    public static function playerStartLine(Player $player, stdClass $data)
    {
        if (!self::$trackers->has($player->id)) {
            self::$trackers->put($player->id, (object)[
                'startServerTime' => $data->time,
                'blockIds'        => []
            ]);
        }
    }
}