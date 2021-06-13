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
     * @param stdClass $data
     */
    public static function playerFinish(Player $player, stdClass $data)
    {
        $blockId = $data->blockid;
        $tracker = self::$trackers->get($player->id);

        if (is_null($tracker)) {
            $tracker = collect();
            $tracker->push((object)[
                'blockId' => $blockId,
                'time'    => $data->racetime
            ]);
            self::$trackers->put($player->id, $tracker);
            Hook::fire('PlayerFinishSection', $player, $data->racetime, $data->curlapcheckpoints, 1);
            return;
        }

        /**
         * @var Collection $tracker
         */
        if ($tracker->contains('blockId', '=', $blockId)) {
            throw new RuntimeException("Player finished section twice in a row.");
        }

        $tracker->push((object)[
            'blockId' => $blockId,
            'time'    => $data->racetime
        ]);

        Hook::fire('PlayerFinishSection', $player, $data->racetime, $data->curlapcheckpoints, $tracker->count());

        if ($tracker->count() == 5) {
            $totalTime = $tracker->sum('time');
            Hook::fire('PlayerFinish', $player, $totalTime, implode(',', $data->curlapcheckpoints));

            self::$trackers->forget($player->id);
        } else {
            self::$trackers->put($player->id, $tracker);
        }
    }
}