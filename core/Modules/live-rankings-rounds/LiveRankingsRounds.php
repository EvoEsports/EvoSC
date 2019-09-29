<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class LiveRankingsRounds implements ModuleInterface
{
    /**
     * @var array
     */
    private static $points;

    /**
     * @var Collection
     */
    private static $scores;

    /**
     * @var Collection
     */
    private static $finished;

    /**
     * @var Collection
     */
    private static $finished2;

    /**
     * @var Collection
     */
    private static $tracker;

    /**
     * @var Collection
     */
    private static $match;

    /**
     * @var Map
     */
    private static $currentMap;

    private static $mapCounter = 0;
    private static $round = 0;

    public static function beginMap(Map $map)
    {
        self::$points = Server::getRoundCustomPoints() ?: [10, 8, 6, 4, 2, 1];
        self::$tracker = collect();
        self::$finished = collect();
        self::$finished2 = collect();
        self::$currentMap = $map;
        self::$mapCounter++;
        self::$round = 0;
    }

    public static function roundStart($data)
    {
        self::$tracker = collect();
        self::$finished = collect();
        self::$finished2 = collect();
        self::$round++;
        self::updateWidget();
    }

    public static function playerConnect(Player $player)
    {
        $points = self::$points;
        Template::show($player, 'live-rankings-rounds.widget', compact('points'));
    }

    public static function updateWidget()
    {
        $trackers = self::$tracker->values()->groupBy('cp')->map(function (Collection $data) {
            return $data->sortBy('score');
        })->toJson();

        Template::showAll('live-rankings-rounds.update', compact('trackers'));
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if (!self::$match->has(self::$mapCounter)) {
            $map = new \stdClass();
            $map->uid = self::$currentMap->uid;
            $map->name = self::$currentMap->name;
            $map->rounds = collect();
            self::$match->put(self::$mapCounter, $map);
        }

        if (!self::$match->get(self::$mapCounter)->rounds->has(self::$round)) {
            self::$match->get(self::$mapCounter)->rounds->put(self::$round, collect());
        }

        if (!self::$scores->has($player->id)) {
            self::$scores->put($player->id, 0);
        }

        $stat = new \stdClass();
        $stat->player_login = $player->Login;
        $stat->player_nick = $player->NickName;
        $stat->score = $score;
        $stat->checkpoints = $checkpoints;
        $stat->add = 0;
        $stat->points = self::$scores->get($player->id);

        if ($score == 0 && !self::$finished->has($player->id)) {
            self::playerCheckpoint($player, 0, 0, true);
        } else {
            $pos = self::$finished->count() + 1;
            $addPoints = 0;

            if ($pos < count(self::$points)) {
                $addPoints = self::$points[$pos];
            }

            $stat->add = $addPoints;
            self::$finished->put('id', $player->id);
            self::$scores->put($player->id, self::$scores->get($player->id) + $addPoints);
        }

        self::$match->get(self::$mapCounter)->rounds->get(self::$round)->push($stat);
        self::updateWidget();
    }


    public static function playerCheckpoint(Player $player, int $score, int $cp, bool $isFinish)
    {
        $tracker = new \stdClass();
        $tracker->score = $score;
        $tracker->cp = $cp + 1;
        $tracker->finished = $isFinish;
        $tracker->nick = $player->NickName;
        $tracker->pos = 0;

        if ($isFinish && $score > 0) {
            self::$finished2->put($player->id, true);
            $tracker->pos = self::$finished2->count();
        }

        self::$tracker->put($player->id, $tracker);
        self::updateWidget();
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        switch ($mode) {
            case 'Rounds.Script.txt':
                if (!$isBoot) {
                    Template::showAll('live-rankings-rounds.widget');
                }
                Hook::add('BeginMap', [self::class, 'beginMap']);
                Hook::add('Maniaplanet.StartRound_Start', [self::class, 'roundStart']);
                Hook::add('PlayerConnect', [self::class, 'playerConnect']);
                Hook::add('PlayerCheckpoint', [self::class, 'playerCheckpoint']);
                Hook::add('PlayerFinish', [self::class, 'playerFinish']);
                self::$scores = collect();
                self::$match = collect();
                break;

            default:
                break;
        }
    }
}