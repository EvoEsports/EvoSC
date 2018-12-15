<?php

namespace esc\Modules;

use esc\Classes\Config;
use esc\Classes\Hook;
use esc\Classes\StatisticWidget;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Karma;
use esc\Models\LocalRecord;
use esc\Models\Player;
use esc\Models\Stats;

class Statistics
{
    /**
     * Statistics constructor.
     */
    public function __construct()
    {
        Hook::add('PlayerConnect', [Statistics::class, 'playerConnect']);
        Hook::add('PlayerFinish', [Statistics::class, 'playerFinish']);
        Hook::add('PlayerRateMap', [Statistics::class, 'playerRateMap']);
        Hook::add('PlayerLocal', [Statistics::class, 'playerLocal']);
        Hook::add('EndMatch', [Statistics::class, 'endMatch']);

        Hook::add('EndMatch', [Statistics::class, 'showScores']);

        Timer::create('update-playtimes', [Statistics::class, 'updatePlaytimes'], '1m');
    }

    public static function showScores(...$args)
    {
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
        $statCollection->push(new StatisticWidget('Score', " Top Ranks", '', ' Points'));

        //Top Donators
        $statCollection->push(new StatisticWidget('Donations', " Top Donators"));

        foreach (onlinePlayers() as $player) {
            Template::show($player, 'statistics.widgets', compact('statCollection'));
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

        if ($player->stats === null) {
            Stats::create([
                'Player' => $player->id,
                'Visits' => 1,
            ]);
        }

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
    public static function playerLocal(Player $player, LocalRecord $local)
    {
        $player->stats()->update([
            'Locals' => $player->locals->count(),
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

        Timer::create('update-playtimes', [Statistics::class, 'updatePlaytimes'], '1m', true);
    }

    /**
     * @param array ...$args
     */
    public static function endMatch(...$args)
    {
        $finishedPlayers = finishPlayers();
        $bestPlayer      = $finishedPlayers->sortBy('Score')->first();

        foreach ($finishedPlayers as $player) {
            self::calculatePlayerServerScore($player);
        }

        self::updatePlayerRanks();

        if ($bestPlayer) {
            $bestPlayer->stats()->increment('Wins');
            ChatController::message(onlinePlayers(), "\$fff🏆", 'Player ', $bestPlayer, ' wins this round. Total wins: ', $bestPlayer->stats->Wins);
        }
    }

    /**
     * @param Player $player
     */
    private static function calculatePlayerServerScore(Player $player)
    {
        $locals = $player->locals;
        $score  = 0;

        $locals->each(function (LocalRecord $local) use (&$score) {
            $score += (100 - $local->Rank);
        });

        $player->stats()->update([
            'Score' => $score,
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
                'Rank' => $counter++,
            ]);

            if ($stats->player->player_id) {
                if ($stats->Rank && $stats->Rank > 0) {
                    ChatController::message($stats->player, '_info', 'Your server rank is ', secondary($stats->Rank . '/' . $total), ' (Score: ', $stats->Score, ')');
                } else {
                    ChatController::message($stats->player, '_info', 'You need at least one local record before receiving a rank.');
                }
            }
        });
    }
}