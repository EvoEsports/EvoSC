<?php

namespace EvoSC\Modules\Statistics;

use Carbon\Carbon;
use Closure;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Classes\Timer;
use EvoSC\Controllers\MapController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\Statistics\Classes\StatisticWidget;
use EvoSC\Modules\Statistics\Models\Stats;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Opis\Closure\SerializableClosure;
use Spatie\Async\Pool;
use Throwable;

class Statistics extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static Collection $scores;

    /**
     * @var int
     */
    private static $totalRankedPlayers = 0;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$totalRankedPlayers = DB::table('stats')->where('Score', '>', 0)->count();

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('PlayerRateMap', [self::class, 'playerRateMap']);

        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('ShowScores', [self::class, 'showScores']);
        Hook::add('AnnounceWinner', [self::class, 'announceWinner']);

        Timer::create('update_playtimes', [self::class, 'updateConnectedPlayerPlaytimes'], '5s', true);

        ChatCommand::add('/rank', [self::class, 'showRank'], 'Show your current server rank.');
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
        $statCollection->push(new StatisticWidget('Playtime', " Most played", '', 'h', function ($sec) {
            //Get playtime as hours
            return round(($sec / 60) / 60, 1);
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

            $statCollection->push(new StatisticWidget('RoundAvg', " Round Average", '', '', null, true, true,
                $averageScores));
            self::$scores = collect();
        }

        //Popular Maps
        $popularMaps = DB::table('maps')->orderByDesc('plays')->where('enabled', 1)->take(6)->pluck('plays', 'name');
        $statCollection->push(new StatisticWidget('PopularMaps', " Most played maps", '', ' plays', null, true, true,
            $popularMaps));

        //Least recently played tracks
        $popularMaps = DB::table('maps')->orderBy('last_played')->whereNotNull('name')->where('enabled',
            1)->take(6)->pluck('last_played', 'name');
        $statCollection->push(new StatisticWidget('LeastRecentlyPlayed', " Least recently played", '', '',
            function ($last_played) {
                return $last_played ? (new Carbon($last_played))->diffForHumans() : 'never';
            }, true, true, $popularMaps));

        $currentMapUid = MapController::getCurrentMap()->uid;

        Template::showAll('Statistics.widgets', compact('statCollection', 'currentMapUid'));

        /**
         * Calculate scores
         */
        $limit = config('locals.limit');

        $data = DB::table('local-records')
            ->join('players', 'local-records.Player', '=', 'players.id')
            ->join('maps', 'local-records.Map', '=', 'maps.id')
            ->selectRaw('Player as id, Login, SUM(Rank) as rank_sum, COUNT(Rank) as locals')
            ->where('maps.enabled', '=', 1)
            ->whereIn('Login', $players->pluck('login'))
            ->groupBy('Login')
            ->get();

        foreach ($data as $stat) {
            DB::table('stats')->updateOrInsert([
                'Player' => $stat->id
            ], [
                'Score' => $limit * $stat->locals - intval($stat->rank_sum),
                'Locals' => $stat->locals
            ]);
        }

        self::$totalRankedPlayers = DB::table('stats')->where('Score', '>', 0)->count();
        self::updatePlayerRanks($players);
    }

    /**
     * Set ranks for players
     *
     * @param Collection $players
     */
    public static function updatePlayerRanks(Collection $players)
    {
        DB::raw('SET @rank=0');
        DB::raw('UPDATE `stats` SET `Rank`= @rank:=(@rank+1) WHERE `Score` > 0 ORDER BY `Score` DESC');

        $playerLogins = $players->pluck('login')->toArray();

        $scores = DB::table('players')
            ->join('stats', 'players.id', '=', 'stats.Player')
            ->whereIn('Login', $playerLogins)
            ->select(['Login', 'Player', 'Rank', 'stats.Score'])
            ->get();

        foreach ($scores as $score) {
            infoMessage('Your server rank is ',
                secondary($score->Rank . '/' . self::$totalRankedPlayers . ' (Score: ' . $score->Score . ')'))->send($score->Login);
        }

        $playersWithoutScores = onlinePlayers()->whereNotIn('Login', $scores->pluck('Login'));

        foreach ($playersWithoutScores as $player) {
            infoMessage('You need at least one local record before receiving a rank.')->send($player->Login);
        }
    }

    public static function showRank(Player $player)
    {
        $stats = $player->stats;

        if ($stats && $stats->Rank && $stats->Rank > 0) {
            infoMessage('Your server rank is ',
                secondary($stats->Rank . '/' . self::$totalRankedPlayers . ' (Score: ' . $stats->Score . ')'))->send($stats->player);
        } else {
            infoMessage('You need at least one local record before receiving a rank.')->send($player);
        }
    }

    /**
     * Announce the winner of the round and increment his win count
     *
     * @param Player $player
     */
    public static function announceWinner(Player $player)
    {
        Log::write('Winner: ' . $player);

        try {
            $player->stats()->increment('Wins');
        } catch (Exception $e) {
            Log::write('Failed to increment win count of ' . $player);
        }

        infoMessage($player, ' wins this round. Total wins: ', ($player->stats->Wins + 1))
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

        DB::table('stats')->where('Player', '=', $player->id)->increment('Visits');
    }

    /**
     * @param Player $player
     * @param int $score
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
     */
    public static function playerRateMap(Player $player)
    {
        $player->Ratings = $player->ratings()->count();
        $player->save();
    }

    /**
     * Increment play-times each minute
     */
    public static function updateConnectedPlayerPlaytimes()
    {
        $onlinePlayerIds = onlinePlayers()->pluck('id');
        Stats::whereIn('Player', $onlinePlayerIds)->increment('Playtime', 5);
    }

    public static function encodeTask($task): string
    {
        if ($task instanceof Closure) {
            $task = new SerializableClosure($task);
        }

        $task = base64_encode(serialize($task));

        return $task;
    }

    /**
     */
    public static function beginMap()
    {
        self::$scores = collect();
    }
}
