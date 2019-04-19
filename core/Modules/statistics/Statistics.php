<?php

namespace esc\Modules;

use esc\Classes\Database;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\StatisticWidget;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Models\Karma;
use esc\Models\LocalRecord;
use esc\Models\Player;
use esc\Models\Stats;
use Illuminate\Support\Collection;

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
        Hook::add('ShowScores', [self::class, 'showScores']);
        Hook::add('AnnounceWinner', [self::class, 'announceWinner']);

        Timer::create('update_playtimes', [self::class, 'updateConnectedPlayerPlaytimes'], '5s', true);
    }

    public static function showScores(Collection $players)
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
            return round($min / (60 * 5), 1);
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

        $players = $players->sortBy('bestracetime');

        $limit = config('locals.limit');
        $players->each(function ($player_) use ($limit) {
            $player      = player($player_->login, true);
            $localsCount = $player->locals()->where('Rank', '<', $limit)->count();
            $rankSum     = $player->locals()->where('Rank', '<', $limit)->select('Rank')->get()->sum('Rank');
            $player->stats()->update(['Score' => $limit * $localsCount - $rankSum]);
        });

        self::$totalRankedPlayers = $players->count();
        self::updatePlayerRanks($players);
        Player::where('Score', '>', 0)->update(['Score' => 0]);
    }

    /**
     * Set ranks for players
     *
     * @param \Illuminate\Support\Collection $players
     */
    private static function updatePlayerRanks(Collection $players)
    {
        Log::logAddLine('Statistics', 'Updating player-ranks.', isVeryVerbose());

        Database::getConnection()->statement('SET @rank=0');
        Database::getConnection()->statement('UPDATE `stats` SET `Rank`= @rank:=(@rank+1) WHERE `Score` > 0 ORDER BY `Score` DESC');

        Log::logAddLine('Statistics', 'Updating player-ranks finished.', isVeryVerbose());

        return;

        var_dump($players->pluck('login')->toArray());

        $playerIds    = $players->whereIn('Login', $players->pluck('login')->toArray())->pluck('id');

        var_dump($playerIds->toArray());

        $playerScores = Stats::select(['Player', 'Rank', 'Score'])->whereIn('Player', $playerIds->toArray())->get();

        var_dump($playerScores);
    }

    public static function showRank(Player $player)
    {
        $stats = $player->stats;

        if ($stats && $stats->Rank && $stats->Rank > 0) {
            infoMessage('Your server rank is ', secondary($stats->Rank . '/' . self::$totalRankedPlayers . ' (Score: ' . $stats->Score . ')'))->send($stats->player);
        } else {
            infoMessage('You need at least one local record before receiving a rank.')->send($player);
        }
    }

    /**
     * Announce the winner of the round and increment his win count
     *
     * @param \esc\Models\Player $player
     */
    public static function announceWinner(Player $player)
    {
        Log::logAddLine('Statistics', 'Winner: ' . $player);

        try {
            $player->stats()->increment('Wins');
        } catch (\Exception $e) {
            Log::logAddLine('Statistics', 'Failed to increment win count of ' . $player);
        }

        infoMessage($player, ' wins this round. Total wins: ', $player->stats->Wins)
            ->setIcon('🏆')
            ->sendAll();
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