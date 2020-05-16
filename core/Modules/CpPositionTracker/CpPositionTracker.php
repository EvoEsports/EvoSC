<?php

namespace EvoSC\Modules\CpPositionTracker;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;
use stdClass;

class CpPositionTracker extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static Collection $tracker;

    public static function showManialink(Player $player)
    {
        self::sendTrackerData();
        Template::show($player, 'cp-position-tracker.manialink');
    }

    public static function sendTrackerData()
    {
        $data = self::$tracker->groupBy('cp')->sortKeysDesc()->map(function (Collection $group) {
            return $group->sortBy('score');
        });

        Template::showAll('cp-position-tracker.update', compact('data'));
    }

    public static function beginMap()
    {
        self::$tracker = collect();

        self::sendTrackerData();
    }

    public static function trackerResetPlayer(Player $player)
    {
        self::$tracker->forget($player->id);
        self::sendTrackerData();
    }

    public static function playerCheckpoint(Player $player, int $score, int $cp, bool $isFinish)
    {
        $o = new stdClass();
        $o->name = $player->NickName;
        $o->score = $score;
        $o->cp = $cp + 1;
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
        Hook::add('PlayerStartCountdown', [self::class, 'trackerResetPlayer']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
    }
}