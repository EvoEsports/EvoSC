<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\Cache;
use EvoSC\Classes\Controller;
use EvoSC\Classes\Hook;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;

class MatchController extends Controller implements ControllerInterface
{
    const CACHE_ID = 'match_controller';

    private static ?Collection $tracker;
    private static ?Collection $roundTracker;
    private static array $pointsRepartition = [];

    /**
     * Initialize MatchController
     */
    public static function init()
    {
        self::$tracker = collect();
        self::$roundTracker = collect();
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        if (Cache::has(self::CACHE_ID)) {
            self::$tracker = collect(Cache::get(self::CACHE_ID));
            Cache::forget(self::CACHE_ID);
        }

        self::$pointsRepartition = PointsController::getPointsRepartition();

        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMatch', [self::class, 'resetWidget']);
        Hook::add('BeginMap', [self::class, 'resetWidget']);
        Hook::add('Maniaplanet.StartPlayLoop', [self::class, 'resetRoundTracker']);
    }

    /**
     * @throws \Exception
     */
    public static function stop()
    {
        Cache::put(self::CACHE_ID, self::$tracker->toArray(), now()->addMinute());
    }

    /**
     * @param Player $player
     * @param int $score
     * @param string $checkpoints
     */
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if (ModeController::isTimeAttackType()) {
            if ($score == 0) {
                return;
            } else if (self::$tracker->has($player->id)) {
                if (self::$tracker->get($player->id)->score <= $score) {
                    return;
                }
            }

            self::$tracker->put($player->id, (object)[
                'login' => $player->Login,
                'name' => $player->NickName,
                'score' => $score,
                'checkpoints' => $checkpoints,
                'points' => 0,
                'gained' => 0,
            ]);
        } else {
            $gainedPoints = 0;
            self::$roundTracker->push($player->id);

            if ($score > 0) {
                $pointsRepartitionSize = count(self::$pointsRepartition);
                $roundPlacement = self::$roundTracker->count() - 1;

                if ($pointsRepartitionSize > 0 && $roundPlacement < $pointsRepartitionSize) {
                    $gainedPoints = self::$pointsRepartition[$roundPlacement];
                }
            }

            if (self::$tracker->has($player->id)) {
                $tracker = self::$tracker->get($player->id);
                $tracker->points += $gainedPoints;
                $tracker->gained = $gainedPoints;
                $tracker->score = $score;
                $tracker->checkpoints = $checkpoints;
                self::$tracker->put($player->id, $tracker);
            } else {
                self::$tracker->put($player->id, (object)[
                    'login' => $player->Login,
                    'name' => $player->NickName,
                    'score' => $score,
                    'checkpoints' => $checkpoints,
                    'points' => $gainedPoints,
                    'gained' => $gainedPoints,
                ]);
            }
        }

        Hook::fire('MatchTrackerUpdated', self::$tracker->values());
    }

    public static function resetRoundTracker()
    {
        self::$roundTracker = collect();
        self::$tracker->transform(function ($tracker) {
            $tracker->gained = 0;
            return $tracker;
        });
        Hook::fire('MatchTrackerUpdated', self::$tracker->values());
    }

    /**
     * @param null $map
     */
    public static function resetWidget($map = null)
    {
        self::$tracker = collect();
        self::$roundTracker = collect();

        Hook::fire('MatchTrackerUpdated', self::$tracker->values());
    }

    /**
     * @return Collection|null
     */
    public static function getTracker(): ?Collection
    {
        return self::$tracker;
    }
}