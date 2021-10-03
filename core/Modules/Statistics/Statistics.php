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
        if (config('statistics.Visits.enabled'))
          $statCollection->push(new StatisticWidget('Visits', " Top visitors"));

        //Most played
        if (config('statistics.Playtime.enabled'))
          $statCollection->push(new StatisticWidget('Playtime', " Most played", '', 'h', function ($sec) {
              //Get playtime as hours
              return round(($sec / 60) / 60, 1);
          }));

        //Most finishes
        if (config('statistics.Finishes.enabled'))
          $statCollection->push(new StatisticWidget('Finishes', "🏁 Most Finishes"));

        //Top winners
        if (config('statistics.Wins.enabled'))
          $statCollection->push(new StatisticWidget('Wins', " Top Winners"));

        //Top Ranks
        if (config('statistics.Rank.enabled'))
          $statCollection->push(new StatisticWidget('Rank', " Top Ranks", '', '.', null, true, false));

        //Top Planets-Donators
        if (config('statistics.Donations.enabled') && isManiaPlanet()) {
            $statCollection->push(new StatisticWidget('Donations', " Top Donators", '', ' Planets'));
        }

        //Round average
        if (config('statistics.RoundAvg.enabled') && self::$scores->count() > 0) {
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

        //Popular Maps
        if (config('statistics.PopularMaps.enabled'))
        {
          $popularMaps = DB::table('maps')->orderByDesc('plays')->where('enabled', 1)->take(6)->pluck('plays', 'name');
          $statCollection->push(new StatisticWidget('PopularMaps', " Most played maps", '', ' plays', null, true, true, $popularMaps));
        }


        //Least recently played tracks
        if (config('statistics.LeastRecentlyPlayed.enabled'))
        {
          $popularMaps = DB::table('maps')->orderBy('last_played')->whereNotNull('name')->where('enabled', 1)->take(6)->pluck('last_played', 'name');
          $statCollection->push(new StatisticWidget('LeastRecentlyPlayed', " Least recently played", '', '', function ($last_played) {
                  return $last_played ? (new Carbon($last_played))->diffForHumans() : 'never';
              }, true, true, $popularMaps));
        }

        $currentMapUid = MapController::getCurrentMap()->uid;

        Template::showAll('Statistics.widgets', compact('statCollection', 'currentMapUid'));

        /**
         * Calculate scores
         */
        $limit = config('locals.limit');


        DB::raw('UPDATE stats SET Score = 0, Locals = 0, `Rank` = -1 WHERE 1=1;');

        DB::raw('UPDATE stats
JOIN (
    SELECT Player, COUNT(`Rank`) AS total_locals FROM `local-records`
    LEFT JOIN maps ON maps.id = `local-records`.Map
    WHERE maps.enabled = 1
    GROUP BY Player
        ) s
ON s.Player = stats.Player
SET stats.Locals = s.total_locals
WHERE 1=1;');

        DB::raw('UPDATE stats
JOIN (
        SELECT stats.Player, ((' . $limit . '*Locals)-SUM(`local-records`.`Rank`)) AS score FROM `local-records`
        LEFT JOIN maps ON maps.id = `local-records`.Map
        LEFT JOIN stats ON stats.Player = `local-records`.Player
        WHERE maps.enabled = 1
        GROUP BY Player
        ) s
ON s.Player = stats.Player
SET stats.Score = s.score
WHERE 1=1;');

        self::$totalRankedPlayers = DB::table('stats')->where('Score', '>', 0)->count();

        DB::raw('SET @rank=0');
        DB::raw('UPDATE `stats` SET `Rank`= @rank:=(@rank+1) WHERE `Score` > 0 ORDER BY `Score` DESC');

        $scores = DB::table('players')
            ->join('stats', 'players.id', '=', 'stats.Player')
            ->select(['Login', 'Rank', 'stats.Score'])
            ->whereIn('Login', $players->pluck('login'))
            ->get();

        foreach ($scores as $score) {
            if($score->Rank == -1){
                infoMessage('You need at least one local record before receiving a rank.')->send($score->Login);
            }else{
                infoMessage('Your server rank is ',
                    secondary($score->Rank . '/' . self::$totalRankedPlayers . ' (Score: ' . $score->Score . ')'))->send($score->Login);
            }
        }
    }

    /**
     * Set ranks for players
     *
     * @param Collection $players
     */
    public static function updatePlayerRanks(Collection $players)
    {
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
            Log::errorWithCause('Failed to increment win count of ' . $player, $e);
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
        $player->stats->Ratings = $player->ratings()->count();
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

        Template::hideAll('Statistics');
    }
}
