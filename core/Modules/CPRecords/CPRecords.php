<?php

namespace EvoSC\Modules\CPRecords;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\CPRecords\Classes\CPRecordsTracker;
use Illuminate\Support\Collection;

class CPRecords extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static Collection $tracker;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$tracker = collect();

        $cpMode = config('cp-records.mode', 'best-cp');

        if ($cpMode == 'best-round') {
            Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        } else {
            Hook::add('PlayerCheckpoint', [self::class, 'playerCheckpoint']);
        }

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('EndMap', [self::class, 'beginMatch']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);
    }

    /**
     * @param Player $player
     * @param int $time
     * @param int $cpId
     * @param bool $isFinish
     */
    public static function playerCheckpoint(Player $player, int $time, int $cpId, bool $isFinish)
    {
        if ($time < 500) {
            return;
        }

        if (self::$tracker->has($cpId)) {
            if (self::$tracker->get($cpId)->time <= $time) {
                return;
            }
        }

        self::$tracker->put($cpId, new CPRecordsTracker($cpId, $player->NickName, $time, $isFinish));
        self::sendUpdatedCpRecords($cpId);
    }

    /**
     * @param Player $player
     * @param int $time_
     * @param string $checkpoints
     */
    public static function playerFinish(Player $player, int $time_, string $checkpoints)
    {
        if ($time_ == 0) {
            return;
        }

        if (self::$tracker->count() > 0) {
            if (self::$tracker->last()->time <= $time_) {
                dump("nope");
                return;
            }
        }

        $times = explode(',', $checkpoints);
        $last = array_pop($times);
        $i = 0;
        foreach ($times as $i => $time) {
            self::$tracker->put($i, new CPRecordsTracker($i, $player->NickName, $time, false));
        }
        self::$tracker->put($i + 1, new CPRecordsTracker($i + 1, $player->NickName, $last, true));
        self::sendUpdatedCpRecords();
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        self::sendUpdatedCpRecords();
        Template::show($player, 'cp-records.widget');
    }

    /**
     * @param int $updatedCpId
     */
    public static function sendUpdatedCpRecords(int $updatedCpId = -1)
    {
        $data = self::$tracker->values()->toJson();
        Template::showAll('cp-records.update', compact('data', 'updatedCpId'));
    }

    /**
     *
     */
    public static function beginMatch()
    {
        self::$tracker = collect();
        Template::showAll('cp-records.widget');
    }
}