<?php

namespace EvoSC\Modules\MatchTracker;


use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;
use stdClass;

class MatchTracker extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static Collection $match;

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
//        if ($mode == 'Rounds.Script.txt') {
//            if (!$isBoot) {
//                Template::showAll('match-tracker.widget');
//            }
//
//            self::$match = collect();
//            Hook::add('PlayerConnect', [self::class, 'sendWidget']);
//            Hook::add('PlayerCheckpoint', [self::class, 'playerCheckpoint']);
//            Hook::add('PlayerFinish', [self::class, 'playerFinish']);
//            Hook::add('Maniaplanet.StartRound_Start', [self::class, 'resetTracker']);
//            Hook::add('Trackmania.WarmUp.StartRound', [self::class, 'resetTracker']);
//        } else {
//            if (!$isBoot) {
//                Template::hideAll('match-tracker-widget');
//            }
//        }
    }

    public static function sendWidget(Player $player)
    {
        $points = Server::getRoundCustomPoints();

        if (!$points) {
            $points = [10, 8, 6, 4, 2, 1];
        }

        Template::show($player, 'MatchTracker.widget', compact('points'));
    }

    public static function resetTracker()
    {
        $trackers = '[]';
        Template::showAll('MatchTracker.update', compact('trackers'));
    }

    public static function playerCheckpoint(Player $player, int $score, int $cp, bool $isFinish)
    {
        if (self::$match->has($player->id)) {
            $tracker = self::$match->get($player->id);
        } else {
            $tracker = new stdClass();
            $tracker->nick = $player->NickName;
            $tracker->points = 0;
        }

        $tracker->cp = $cp + 1;
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
                $tracker = new stdClass();
                $tracker->nick = $player->NickName;
                $tracker->points = 0;
            }

            $tracker->cp = -1;

            self::$match->put($player->id, $tracker);
            self::updateWidget();
        }
    }

    private static function updateWidget()
    {
        $trackers = self::$match->values()->groupBy('cp')->map(function (Collection $data) {
            return $data->sortBy('score');
        })->toJson();

        Template::showAll('MatchTracker.update', compact('trackers'));
    }
}