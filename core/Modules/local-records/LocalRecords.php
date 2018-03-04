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

            if ($score == $local->Score) {
                ChatController::messageAllNew('Player ', $player, ' equaled his/hers ', $local);
                return;
            }

            if ($score < $local->Score) {
                $diff = $local->Score - $score;
                $rank = self::getRank($map, $score);

                if ($rank != $local->Rank) {
                    self::pushDownRanks($map, $rank);
                    $local->update(['Score' => $score, 'Rank' => $rank]);
                    ChatController::messageAllNew('Player ', $player, ' gained the ', $local, ' (-' . formatScore($diff) . ')');
                } else {
                    $local->update(['Score' => $score]);
                    ChatController::messageAllNew('Player ', $player, ' gained the ', $local, ' (-' . formatScore($diff) . ')');
                }
            }
        } else {
            if ($localsCount < 100) {
                $worstLocal = $map->locals()->orderByDesc('Score')->first();

                if ($worstLocal) {
                    if ($score <= $worstLocal->Score) {
                        self::pushDownRanks($map, $worstLocal->Rank);
                        $local = self::pushLocal($map, $player, $score, $worstLocal->Rank);
                        ChatController::messageAllNew('Player ', $player, ' gained the ', $local);
                    }else{
                        $local = self::pushLocal($map, $player, $score, $worstLocal->Rank + 1);
                        ChatController::messageAllNew('Player ', $player, ' made the ', $local);
                    }
                } else {
                    $rank = 1;
                    $local = self::pushLocal($map, $player, $score, $rank);
                    ChatController::messageAllNew('Player ', $player, ' made the ', $local);
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
        $map->locals()->where('Rank', '>=', $startRank)->orderByDesc('Rank')->increment('Rank');
    }

    private static function getRank(Map $map, int $score): ?int
    {
        $nextBetter = $map->locals->where('Score', '<=', $score)->sortByDesc('Score')->first();

        if ($nextBetter) {
            return $nextBetter->Rank + 1;
        }

        return 1;
    }

    public static function beginMap()
    {
        self::displayLocalRecords();
    }

    public static function displayLocalRecords()
    {
        $locals = MapController::getCurrentMap()
            ->locals()
            ->orderBy('Rank')
            ->get()
            ->take(config('ui.locals.rows'));

        Template::showAll('esc.box', [
            'id' => 'Locals',
            'title' => 'local records',
            'x' => config('ui.locals.x'),
            'y' => config('ui.locals.y'),
            'rows' => config('ui.locals.rows'),
            'scale' => config('ui.locals.scale'),
            'content' => Template::toString('locals', ['locals' => $locals])
        ]);
    }
}