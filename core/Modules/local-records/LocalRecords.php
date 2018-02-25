<?php

use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\Template;
use esc\classes\Timer;
use esc\controllers\ChatController;
use esc\controllers\MapController;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Database\Schema\Blueprint;

class LocalRecords
{
    public function __construct()
    {
        $this->createTables();

        include_once 'Models/LocalRecord.php';

        Template::add('locals', File::get(__DIR__ . '/Templates/locals.latte.xml'));

        Hook::add('PlayerFinish', 'LocalRecords::playerFinish');
        Hook::add('BeginMap', 'LocalRecords::beginMap');
        Hook::add('PlayerConnect', 'LocalRecords::beginMap');
    }

    private function createTables()
    {
        Database::create('local-records', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('Player');
            $table->integer('Map');
            $table->integer('Score');
            $table->integer('Rank');
            $table->unique(['Map', 'Rank']);
        });
    }

    private static function playerHasLocal(Map $map, Player $player): bool
    {
        return LocalRecord::whereMap($map->id)->wherePlayer($player->id)->first() != null;
    }

    public static function playerFinish(Player $player, int $score)
    {
        if ($score == 0) {
            return;
        }

        $map = MapController::getCurrentMap();

        $localsCount = $map->locals()->count();

        if (self::playerHasLocal($map, $player)) {
            $local = $map->locals()->wherePlayer($player->id)->first();
            if ($score < $local->Score) {
                $diff = $local->Score - $score;
                $rank = self::getRank($map, $score);

                if ($rank) {
                    self::pushDownRanks($map, $rank);
                    $local = self::pushLocal($map, $player, $score, $rank);
                } else {
                    $local->update(['Score' => $score]);
                }

                ChatController::messageAllNew($player, ' gained ', $local, ' (', formatScore(-$diff), ')');
            }
        } else {
            if ($localsCount < 100) {
                $worstLocal = $map->locals()->orderByDesc('Score')->first();

                if ($worstLocal) {
                    $rank = $worstLocal->Rank;
                    if ($score < $worstLocal->Score) {
                        self::pushDownRanks($map, $rank);
                        $local = self::pushLocal($map, $player, $score, $rank);
                        ChatController::messageAllNew($player, ' gained ', $local);
                    }
                } else {
                    $rank = 1;
                    $local = self::pushLocal($map, $player, $score, $rank);
                    ChatController::messageAllNew($player, ' made ', $local);
                }
            }
        }

        self::displayLocalRecords();
    }

    private static function pushLocal(Map $map, Player $player, int $score, int $rank): LocalRecord
    {
        $map->locals()->create([
            'Player' => $player->id,
            'Map' => $map->id,
            'Score' => $score,
            'Rank' => $rank,
        ]);

        return $map->locals()->whereRank($rank)->first();
    }

    private static function pushDownRanks(Map $map, int $startRank)
    {
        $map->locals()->where('Rank', '>=', $startRank)->increment('Rank');
    }

    private static function getRank(Map $map, int $score): ?int
    {
        $nextBetter = $map->locals()->where('Score', '<=', $score)->orderByDesc('Score')->get()->first();

        if ($nextBetter) {
            return $nextBetter->Rank;
        }

        return null;
    }

    public static function beginMap()
    {
        self::displayLocalRecords();
    }

    public static function displayLocalRecords()
    {
        $map = MapController::getCurrentMap();
        Template::showAll('locals', ['locals' => $map->locals()->orderBy('Score')->get()->take(13)]);
    }
}