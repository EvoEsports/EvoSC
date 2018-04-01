<?php

use esc\Classes\Database;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;

class Statistics
{
    /**
     * Statistics constructor.
     */
    public function __construct()
    {
        include_once __DIR__ . '/Models/Stats.php';

        self::createTables();

        Hook::add('PlayerConnect', 'Statistics::playerConnect');
        Hook::add('PlayerFinish', 'Statistics::playerFinish');
        Hook::add('PlayerRateMap', 'Statistics::playerRateMap');
        Hook::add('PlayerLocal', 'Statistics::playerLocal');
        Hook::add('PlayerDonate', 'Statistics::playerDonate');
        Hook::add('EndMatch', 'Statistics::endMatch');
        Hook::add('PlayerStartCountdown', 'Statistics::playerStartCountdown');

        Timer::create('update-playtimes', 'Statistics::updatePlaytimes', '1m');
    }

    public static function displayStats(Player $player)
    {
        $statsConfig = config('ui.stats');

        $visitsConfig = $statsConfig->visits;
        $showMostVisits = $visitsConfig->show;
        $mostVisitsValues = Stats::orderByDesc('Visits')->take($showMostVisits)->get();
        $mostVisits = Template::toString('esc.stat-list', [
            'width' => $visitsConfig->width,
            'values' => $mostVisitsValues,
            'value_func' => function (Stats $stats) {
                return $stats->Visits;
            }
        ]);
        $mostVisitsHeight = $showMostVisits * 4.2 + 8;

        Template::show($player, 'esc.box2', [
            'id' => 'stats_visits',
            'title' => 'Top visitors',
            'x' => $visitsConfig->pos->x,
            'y' => $visitsConfig->pos->y,
            'scale' => $visitsConfig->scale,
            'width' => $visitsConfig->width,
            'height' => $mostVisitsHeight,
            'content' => $mostVisits
        ]);

        $playtimeConfig = $statsConfig->playtime;
        $showMostPlayed = $playtimeConfig->show;
        $mostPlayedValues = Stats::orderByDesc('Playtime')->take($showMostPlayed)->get();
        $mostPlayed = Template::toString('esc.stat-list', [
            'width' => $playtimeConfig->width,
            'values' => $mostPlayedValues,
            'value_func' => function (Stats $stats) {
                $hours = $stats->Playtime / 60;
                return ($hours >= 1 ? round($hours, 1) . 'h' : $stats->Playtime . 'min');
            }
        ]);
        $mostPlayedHeight = $showMostPlayed * 4.2 + 8;

        Template::show($player, 'esc.box2', [
            'id' => 'stats_playtime',
            'title' => 'Most played',
            'x' => $playtimeConfig->pos->x,
            'y' => $playtimeConfig->pos->y,
            'scale' => $playtimeConfig->scale,
            'width' => $playtimeConfig->width,
            'height' => $mostPlayedHeight,
            'content' => $mostPlayed
        ]);
    }

    public static function playerStartCountdown(Player $player)
    {
        self::displayStats($player);
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
        $player->Locals = $player->locals()->count();
        $player->save();
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
        $bestPlayer = finishPlayers()->sortBy('Score')->first();

        if ($bestPlayer) {
            $bestPlayer->stats()->increment('Wins');
        }
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
            $table->timestamps();
        });
    }
}