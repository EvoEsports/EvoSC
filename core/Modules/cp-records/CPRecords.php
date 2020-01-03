<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;
use Illuminate\Support\Collection;

class CPRecords implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static $tracker;

    public static function playerConnect(Player $player)
    {
        $cps = self::$tracker->map(function ($tracker, $key) {
            return [
                'index' => $key,
                'name' => $tracker->player->NickName,
                'time' => $tracker->time
            ];
        })->sortBy('index');

        Template::show($player, 'cp-records.widget', compact('cps'));
    }

    public static function playerCheckpoint(Player $player, $time, $cpId)
    {
        if (self::$tracker->has($cpId) && self::$tracker->get($cpId)->time <= $time) {
            return;
        }

        $o = new \stdClass();
        $o->nick = $player->NickName;
        $o->time = $time;

        self::$tracker->put($cpId, $o);
    }

    public static function beginMatch()
    {
        self::$tracker = collect();
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$tracker = collect();

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerCheckpoint', [self::class, 'playerCheckpoint']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);
    }
}