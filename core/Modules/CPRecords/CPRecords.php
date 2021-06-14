<?php

namespace EvoSC\Modules\CPRecords;

use EvoSC\Classes\Cache;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\CPRecords\Classes\CPRecordsTracker;
use EvoSC\Modules\LocalRecords\LocalRecords;
use Illuminate\Support\Collection;

class CPRecords extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static Collection $tracker;

    /**
     * @var int
     */
    private static int $checkpointsPerLap = -1;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (Cache::has('cp_records_current')) {
            self::$tracker = collect(Cache::get('cp_records_current'));
            Cache::forget('cp_records_current');
        } else {
            self::$tracker = collect();
        }

        if (ModeController::isRoyal()) {
            Hook::add('PlayerFinishSegment', [self::class, 'playerFinishSegment']);
        } else {
            if (config('cp-records.mode', 'best-cp') == 'best-round') {
                Hook::add('PlayerFinish', [self::class, 'playerFinish']);
            } else {
                Hook::add('PlayerCheckpoint', [self::class, 'playerCheckpoint']);
            }
        }

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('EndMap', [self::class, 'beginMatch']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);
    }

    public function stop()
    {
        Cache::put('cp_records_current', self::$tracker->toArray(), now()->addMinute());
    }

    public static function playerFinishSegment(Player $player, int $timeInMs, int $segment)
    {
        if (!self::$tracker->has($segment)) {
            self::$tracker->put($segment, (object)[
                'section' => $segment,
                'time'    => $timeInMs,
                'name'    => $player->NickName
            ]);
        } else {
            $tracker = self::$tracker->get($segment);

            if ($timeInMs >= $tracker->time) {
                return;
            }

            self::$tracker->put($segment, (object)[
                'section' => $segment,
                'time'    => $timeInMs,
                'name'    => $player->NickName
            ]);
        }

        self::sendUpdatedRoyalRecord($segment);
    }

    /**
     * @param Map $map
     */
    public static function beginMap(Map $map)
    {
        $checkpoints = MapController::getCurrentMap()->gbx->CheckpointsPerLaps;

        if ($checkpoints == -1) {
            $record = DB::table(LocalRecords::TABLE)->where('Map', '=', $map->id)->first();

            if ($record) {
                $checkpoints = count(explode(',', $record->Checkpoints));
            }
        }

        if ($checkpoints == -1) {
            $checkpoints = 50;
        }

        self::$checkpointsPerLap = $checkpoints;
    }

    /**
     * @param Player $player
     * @param int $time
     * @param int $cpId
     * @param bool $isFinish
     */
    public static function playerCheckpoint(Player $player, int $time, int $cpId, bool $isFinish)
    {
        if ($time < 500 || $cpId > self::$checkpointsPerLap) {
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
     * @param int $segment
     */
    private static function sendUpdatedRoyalRecord(int $segment = -1)
    {
        $data = self::$tracker->values()->toJson();
        Template::showAll('CPRecords.update_royal', compact('data', 'segment'));
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function playerConnect(Player $player)
    {
        if (ModeController::isRoyal()) {
            self::sendUpdatedRoyalRecord();
            Template::show($player, 'CPRecords.widget_royal');
        } else {
            self::sendUpdatedCpRecords();
            Template::show($player, 'CPRecords.widget');
        }
    }

    /**
     * @param int $updatedCpId
     */
    public static function sendUpdatedCpRecords(int $updatedCpId = -1)
    {
        $data = self::$tracker->take(self::$checkpointsPerLap)->values()->toJson();
        Template::showAll('CPRecords.update', compact('data', 'updatedCpId'));
    }

    /**
     *
     */
    public static function beginMatch()
    {
        self::$tracker = collect();
        if (ModeController::isRoyal()) {
            $data = self::$tracker->values()->toJson();
            $segment = 0;
            Template::showAll('CPRecords.update_royal', compact('data', 'segment'));
            Template::showAll('CPRecords.widget_royal');
        } else {
            self::sendUpdatedCpRecords();
            Template::showAll('CPRecords.widget');
        }
    }
}