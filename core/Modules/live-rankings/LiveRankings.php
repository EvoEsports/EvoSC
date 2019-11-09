<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\ScoreController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;
use Illuminate\Support\Collection;

class LiveRankings implements ModuleInterface
{
    private static $show;

    public static function playerConnect(Player $player)
    {
        $liveRankings = ScoreController::getTracker()->where('best_score', '>', 0)
            ->sortBy('best_score')
            ->take(self::$show)
            ->transform(function ($score) {
                $score->nick = ml_escape($score->player->NickName);
                return $score;
            })
            ->pluck('nick', 'best_score');

        Template::show($player, 'live-rankings.update', compact('liveRankings'));
        Template::show($player, 'live-rankings.widget');
    }

    public static function scoresUpdated(Collection $scores)
    {
        $liveRankings = $scores->where('best_score', '>', 0)
            ->sortBy('best_score')
            ->take(self::$show)
            ->transform(function ($score) {
                $score->nick = ml_escape($score->player->NickName);
                $score->score = $score->best_score . '';
                return $score;
            })
            ->pluck('nick', 'score');

        Template::showAll('live-rankings.update', compact('liveRankings'));
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$show = config('live-rankings.show', 14);

        switch ($mode) {
            case 'TimeAttack.Script.txt':
                if (!$isBoot) {
                    Template::showAll('live-rankings.widget');
                }
                Hook::add('PlayerConnect', [self::class, 'playerConnect']);
                Hook::add('ScoresUpdated', [self::class, 'scoresUpdated']);
                break;

            default:
                break;
        }
    }
}