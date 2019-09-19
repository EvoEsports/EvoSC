<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class MatchTracker implements ModuleInterface
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $match;

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if ($mode == 'Rounds.Script.txt') {
            self::$match = collect();
            Hook::add('PlayerConnect', [self::class, 'sendWidget']);
            Hook::add('PlayerCheckpoint', [self::class, 'playerCheckpoint']);
            Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        }
    }

    public static function sendWidget(Player $player)
    {
        Template::show($player, 'match-tracker.widget');
    }

    public static function playerCheckpoint(Player $player, int $score, int $cp, bool $isFinish)
    {
        if (self::$match->has($player->id)) {
            $tracker = self::$match->get($player->id);
        } else {
            $tracker = new \stdClass();
            $tracker->nick = $player->NickName;
            $tracker->points = 0;
        }

        $tracker->cp = $cp;
        $tracker->finished = $isFinish;
        $tracker->score = $score;

        self::$match->put($player->id, $tracker);
        self::updateWidget();
    }

    public static function playerFinish(Player $player, int $score)
    {
        if ($score == 0) {
            if (self::$match->has($player->id)) {
                $tracker = self::$match->get($player->id);
            } else {
                $tracker = new \stdClass();
                $tracker->nick = $player->NickName;
                $tracker->points = 0;
            }

            $tracker->score = -1;

            self::$match->put($player->id, $tracker);
            self::updateWidget();
        }
    }

    private static function updateWidget()
    {
        $trackers = self::$match->values()->toJson();

        Template::showAll('match-tracker.update', compact('trackers'));
    }
}