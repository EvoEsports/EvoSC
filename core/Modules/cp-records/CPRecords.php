<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;
use esc\Modules\Classes\CpRecordsTracker;
use Illuminate\Support\Collection;
use stdClass;

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
     */
    public static function playerCheckpoint(Player $player, int $time, int $cpId)
    {
        if (self::$tracker->has($cpId)) {
            if (self::$tracker->get($cpId)->time <= $time) {
                return;
            }
        }

        self::$tracker->put($cpId, new CpRecordsTracker($cpId, $player->NickName, $time));
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
        foreach ($times as $i => $time) {
            self::$tracker->put($i, new CpRecordsTracker($i, $player->NickName, $time));
        }
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
    }
}