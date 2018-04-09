<?php

use esc\Classes\Database;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\ChatController;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;

class Statistics
{
    /**
     * Statistics constructor.
     */
    public function __construct()
    {
//        include_once __DIR__ . '/Models/Stats.php';

        self::createTables();

        Hook::add('PlayerConnect', 'Statistics::playerConnect');
        Hook::add('PlayerFinish', 'Statistics::playerFinish');
        Hook::add('PlayerRateMap', 'Statistics::playerRateMap');
        Hook::add('PlayerLocal', 'Statistics::playerLocal');
        Hook::add('PlayerDonate', 'Statistics::playerDonate');
        Hook::add('EndMatch', 'Statistics::endMatch');

        Hook::add('ShowScores', 'Statistics::showScores');
        Hook::add('BeginMap', 'Statistics::endMap');

        Timer::create('update-playtimes', 'Statistics::updatePlaytimes', '1m');
    }

    private static function displayStatsWidget(Player $player, $values, $title, $config, $value_function)
    {
        $content = Template::toString('esc.stat-list', [
            'width' => $config->width,
            'values' => $values,
            'value_func' => $value_function
        ]);

        $height = $config->show * 4.2 + 8;

        Template::show($player, 'esc.box2', [
            'id' => str_slug($title),
            'title' => $title,
            'x' => $config->pos->x,
            'y' => $config->pos->y,
            'scale' => $config->scale,
            'width' => $config->width,
            'height' => $height,
            'content' => $content
        ]);
    }

    public static function showStats(Player $player)
    {
        $statsConfig = config('ui.stats');

        $mostVisits = Stats::orderByDesc('Visits')->take($statsConfig->visits->show)->get();
        self::displayStatsWidget($player,
            $mostVisits,
            'Top visitors',
            $statsConfig->visits,
            function (Stats $stats) {
                return $stats->Visits;
            }
        );

        $mostPlayed = Stats::orderByDesc('Playtime')->take($statsConfig->playtime->show)->get();
        self::displayStatsWidget($player,
            $mostPlayed,
            'Most played',
            $statsConfig->playtime,
            function (Stats $stats) {
                $hours = $stats->Playtime / 60;
                return ($hours >= 1 ? round($hours, 1) . 'h' : $stats->Playtime . 'min');
            }
        );

        $mostFinished = Stats::orderByDesc('Finishes')->take($statsConfig->finish->show)->get();
        self::displayStatsWidget($player,
            $mostFinished,
            'Most finishes',
            $statsConfig->finish,
            function (Stats $stats) {
                return $stats->Finishes;
            }
        );

        $mostRecords = Stats::orderByDesc('Locals')->take($statsConfig->records->show)->get();
        self::displayStatsWidget($player,
            $mostRecords,
            'Most records',
            $statsConfig->records,
            function (Stats $stats) {
                return $stats->Locals;
            }
        );

        $topWinners = Stats::orderByDesc('Wins')->take($statsConfig->winner->show)->get();
        self::displayStatsWidget($player,
            $topWinners,
            'Top winners',
            $statsConfig->winner,
            function (Stats $stats) {
                return $stats->Wins;
            }
        );

//        $topVoters = Stats::orderByDesc('Ratings')->take($statsConfig->voter->show)->get();
//        self::displayStatsWidget($player,
//            $topVoters,
//            'Top voters',
//            $statsConfig->voter,
//            function (Stats $stats) {
//                return $stats->Ratings;
//            }
//        );

        $topRanks = Stats::where('Rank', '>', 0)->orderBy('Rank')->take($statsConfig->topranks->show)->get();
        self::displayStatsWidget($player,
            $topRanks,
            'Top ranks',
            $statsConfig->topranks,
            function (Stats $stats) {
                return $stats->Score;
            }
        );
    }

    public static function showScores(...$args)
    {
        foreach (onlinePlayers() as $player) {
            self::showStats($player);
        }
    }

    public static function endMap(...$args)
    {
        Template::hideAll('top-visitors');
        Template::hideAll('most-played');
        Template::hideAll('most-finishes');
        Template::hideAll('most-records');
        Template::hideAll('top-winners');
        Template::hideAll('top-voters');
        Template::hideAll('top-ranks');
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        if (!$player->stats) {
            $player->stats()->create(['Player' => $player->id]);
        }

        $player->stats()->increment('Visits');
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

        $player->stats()->increment('Finishes');
    }

    /**
     * @param Player $player
     * @param Karma $karma
     */
    public static function playerRateMap(Player $player, Karma $karma)
    {
        $player->Ratings = $player->ratings()->count();
        $player->save();
    }

    /**
     * @param Player $player
     * @param LocalRecord $local
     */
    public static function playerLocal(Player $player, LocalRecord $local)
    {
        $player->stats()->update([
            'Locals' => $player->locals->count()
        ]);
    }

    /**
     * Increment playtimes each minute
     */
    public static function updatePlaytimes()
    {
        foreach (onlinePlayers() as $player) {
            $player->stats()->increment('Playtime');
        }

        Timer::create('update-playtimes', 'Statistics::updatePlaytimes', '1m', true);
    }

    /**
     * @param array ...$args
     */
    public static function endMatch(...$args)
    {
        $finishedPlayers = finishPlayers();
        $bestPlayer = $finishedPlayers->sortBy('Score')->first();

        foreach ($finishedPlayers as $player) {
            self::calculatePlayerServerScore($player);
        }

        self::updatePlayerRanks();

        if ($bestPlayer) {
            $bestPlayer->stats()->increment('Wins');
            ChatController::messageAll('Player ', $bestPlayer, ' wins this round. Total wins: ', $bestPlayer->stats->Wins);
        }
    }

    /**
     * @param Player $player
     */
    private static function calculatePlayerServerScore(Player $player)
    {
        $locals = $player->locals;
        $score = 0;

        $locals->each(function (LocalRecord $local) use (&$score) {
            $score += (100 - $local->Rank);
        });

        $player->stats()->update([
            'Score' => $score
        ]);
    }

    /**
     * Set ranks for players
     */
    private static function updatePlayerRanks()
    {
        $stats = Stats::where('Locals', '>', 0)->orderByDesc('Score')->get();
        $total = $stats->count();

        $counter = 1;

        $stats->each(function (Stats $stats) use (&$counter, $total) {
            $stats->update([
                'Rank' => $counter++
            ]);

            if ($stats->player->Online) {
                if ($stats->Rank && $stats->Rank > 0) {
                    ChatController::message($stats->player, 'Your server rank is ', secondary($stats->Rank . '/' . $total), ' (Score: ', $stats->Score, ')');
                } else {
                    ChatController::message($stats->player, 'You need at least one local record before receiving a rank.');
                }
            }
        });
    }

    /**
     * @param Player $player
     * @param int $amount
     */
    public static function playerDonate(Player $player, int $amount)
    {
        $player->Donations += $amount;
        $player->save();
    }

    /**
     * Create the database table
     */
    public static function createTables()
    {
        Database::create('stats', function (Blueprint $table) {
            $table->integer('Player')->primary();
            $table->integer('Visits')->default(0);
            $table->integer('Playtime')->default(0);
            $table->integer('Finishes')->default(0);
            $table->integer('Locals')->default(0);
            $table->integer('Ratings')->default(0);
            $table->integer('Wins')->default(0);
            $table->integer('Donations')->default(0);
            $table->integer('Score')->default(0);
            $table->integer('Rank')->default(0);
            $table->integer('Score')->default(0);
            $table->timestamps();
        });
    }
}