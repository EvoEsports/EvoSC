<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\StatisticWidget;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Classes\ChatCommand;
use esc\Models\Karma;
use esc\Models\LocalRecord;
use esc\Models\Player;
use esc\Models\Stats;

class Statistics
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $scores;

    /**
     * @var int
     */
    private static $totalRankedPlayers = 0;

    /**
     * Statistics constructor.
     */
    public function __construct()
    {
        self::$totalRankedPlayers = Stats::where('Score', '>', 0)->count();

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('PlayerRateMap', [self::class, 'playerRateMap']);
        Hook::add('PlayerLocal', [self::class, 'playerLocal']);

        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMatch', [self::class, 'showScores']);

        Timer::create('update_playtimes', [self::class, 'updateConnectedPlayerPlaytimes'], '5s', true);
    }

    public static function showScores(...$args)
    {
        /**
         * Prepare widgets
         */
        $statCollection = collect();

        //Top visitors
        $statCollection->push(new StatisticWidget('Visits', " Top visitors"));

        //Most played
        $statCollection->push(new StatisticWidget('Playtime', " Most played", '', 'h', function ($min) {
            //Get playtime as hours
            return round($min / 60, 1);
        }));

        //Most finishes
        $statCollection->push(new StatisticWidget('Finishes', "🏁 Most Finishes"));

        //Top winners
        $statCollection->push(new StatisticWidget('Wins', " Top Winners"));

        //Top Ranks
        $statCollection->push(new StatisticWidget('Rank', " Top Ranks", '', '.', null, true, false));

        //Top Donators
        $statCollection->push(new StatisticWidget('Donations', " Top Donators", '', ' Planets'));

        //Round average
        if (self::$scores->count() > 0) {
            $averageScores = self::$scores->groupBy('nick')->map(function ($scoresArray) {
                $scores = [];

                foreach ($scoresArray as $score) {
                    array_push($scores, $score['time']);
                }

                return sprintf('%.3f', (array_sum($scores) / count($scores)) / 1000);
            })->sort()->take(config('statistics.RoundAvg.show'));
            $statCollection->push(new StatisticWidget('RoundAvg', " Round Average", '', '', null, true, true, $averageScores));
            self::$scores = collect();
        }

        Template::showAll('statistics.widgets', compact('statCollection'));

        /**
         * Calculate scores
         */
        $finishedPlayers          = Player::where('Score', '>', 0)->orderBy('Score')->get();
        self::$totalRankedPlayers = Stats::where('Score', '>', 0)->count();

        Log::logAddLine('Statistics', sprintf('Calculating player scores for %d players.', self::$totalRankedPlayers), isVeryVerbose());

        $limit = config('locals.limit');
        $finishedPlayers->each(function (Player $player) use ($limit) {
            $score = $player->locals()->where('Rank', '<', $limit)->selectRaw($limit . ' - Rank as rank_diff')->get()->sum('rank_diff');
            $player->stats()->update(['Score' => $score]);
        });

        self::updatePlayerRanks();

        Player::where('Score', '>', 0)->update(['Score' => 0]);

        if ($finishedPlayers->count() == 0) {
            //No winner

            return;
        }

        if ($finishedPlayers->count() > 1) {
            if ($finishedPlayers->get(0)->Score == $finishedPlayers->get(1)->Score) {
                //Draw

                return;
            }
        }

        $bestPlayer = $finishedPlayers->first();

        try {
            Stats::where('Player', $bestPlayer->id)->increment('Wins');
        } catch (\Exception $e) {
            Log::logAddLine('Statistics', 'Failed to increment win count of ' . $bestPlayer);
        }

        infoMessage($bestPlayer, ' wins this round. Total wins: ', $bestPlayer->stats->Wins)
            ->setIcon('🏆')
            ->sendAll();
    }

    /**
     * Set ranks for players
     */
    private static function updatePlayerRanks()
    {
        Log::logAddLine('Statistics', 'Updating player-ranks.', isVeryVerbose());
        $start = time() + microtime(true);

        Stats::where('Score', '>', 0)->orderByDesc('Score')->get()->each(function (Stats $stat, $rank) {
            $stat->update([
                'Rank' => $rank + 1,
            ]);
        });

        $end = time() + microtime(true);
        Log::logAddLine('Statistics', sprintf('Updating player-ranks finished. Took %.3fs', $end - $start), isVeryVerbose());

        onlinePlayers()->each(function (Player $player) {
            try {
                self::showRank($player);
            } catch (\Exception $e) {
                Log::logAddLine('Statistics', 'Failed to show rank for player ' . $player);
            }
        });
    }

    public static function showRank(Player $player)
    {
        $stats = $player->stats;

        if ($stats && $stats->Rank && $stats->Rank > 0) {
            infoMessage('Your server rank is ', secondary($stats->Rank . '/' . self::$totalRankedPlayers . ' (Score: ' . $stats->Score . ')'))->send($stats->player);
        } else {
            infoMessage('You need at least one local record before receiving a rank.')->send($stats->player);
        }
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        if ($player->id == null) {
            return;
        }

        self::showRank($player);

        $player->stats()->increment('Visits');
    }

    /**
     * @param Player $player
     * @param int    $score
     */
    public static function playerFinish(Player $player, int $score)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        self::$scores->push([
            'nick' => $player->NickName,
            'time' => $score,
        ]);

        $player->stats()->increment('Finishes');
    }

    /**
     * @param Player $player
     * @param Karma  $karma
     */
    public static function playerRateMap(Player $player, Karma $karma)
    {
        $player->Ratings = $player->ratings()->count();
        $player->save();
    }

    /**
     * @param Player      $player
     * @param LocalRecord $local
     */
    public static function playerLocal(Player $player, $local = null)
    {
        $player->stats()->update([
            'Locals' => $player->locals->count(),
        ]);
    }

    /**
     * Increment play-times each minute
     */
    public static function updateConnectedPlayerPlaytimes()
    {
        $onlinePlayerIds = onlinePlayers()->pluck('id');
        Stats::whereIn('Player', $onlinePlayerIds)->increment('Playtime');
    }

    /**
     * @param mixed ...$args
     */
    public static function beginMap(...$args)
    {
        self::$scores = collect();
    }
}