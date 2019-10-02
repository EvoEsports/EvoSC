<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class CpPositionTracker implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static $tracker;

    public static function showManialink(Player $player)
    {
        self::sendTrackerData();
        Template::show($player, 'cp-position-tracker.manialink');
    }

    public static function sendTrackerData()
    {
        $data = self::$tracker->groupBy('cp')->sortKeysDesc();
        Template::showAll('cp-position-tracker.update', compact('data'));
    }

    public static function beginMap(Map $map)
    {
        self::$tracker = collect();

        self::sendTrackerData();
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score == 0) {
            self::$tracker->forget($player->id);

            self::sendTrackerData();
        }
    }

    public static function playerCheckpoint(Player $player, int $score, int $cp, bool $isFinish)
    {
        $o = new \stdClass();
        $o->score = $score;
        $o->cp = $cp;
        $o->finish = $isFinish;

        self::$tracker->put($player->id, $o);
        self::sendTrackerData();
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'showManialink']);
        Hook::add('PlayerCheckpoint', [self::class, 'playerCheckpoint']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
    }
}