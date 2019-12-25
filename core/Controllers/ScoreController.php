<?php


namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\ScoreTracker;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\Player;
use Illuminate\Support\Collection;

class ScoreController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static $tracker;

    private static $mode;

    private static $points;

    private static $finished = 0;

    /**
     * Method called on controller boot.
     */
    public static function init()
    {
    }

    /**
     * Method called on controller start and mode change
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        self::$mode = $mode;
        self::$tracker = collect();
        self::$points = config('server.rounds.points');

        if (self::isRounds()) {
            Server::setRoundCustomPoints(config('server.rounds.points'));
        }

        //TODO: Check for round start & reset $finished
        Hook::add('BeginMatch', [self::class, 'beginMatch']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
    }

    public static function beginMatch()
    {
        self::$tracker = collect();
    }

    private static function isRounds()
    {
        return self::$mode == 'Rounds.Script.txt';
    }

    /**
     * @param  int  $playerId
     * @return ScoreTracker
     */
    private static function getScoreTracker(int $playerId)
    {
        return self::$tracker->get($playerId);
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score == 0) {
            return;
        }

        if (!self::$tracker->has($player->id)) {
            $tracker = new ScoreTracker($player, $score, $checkpoints);
        } else {
            $tracker = self::getScoreTracker($player->id);

            $tracker->last_score = $score;
            $tracker->last_checkpoints = $checkpoints;

            if ($score < $tracker->best_score) {
                $tracker->best_score = $score;
                $tracker->best_checkpoints = $checkpoints;
            }
        }

        if (self::isRounds()) {
            self::$finished++;

            $tracker->addPoints(self::$points[self::$finished] ?? 0);
        }

        self::$tracker->put($player->id, $tracker);

        if (self::isRounds()) {
            self::$tracker = self::$tracker->sortByDesc('points');
        } else {
            self::$tracker = self::$tracker->sortBy('best_score');
        }

        Hook::fire('ScoresUpdated', self::$tracker);
    }

    public static function getTracker(): Collection
    {
        return self::$tracker;
    }
}