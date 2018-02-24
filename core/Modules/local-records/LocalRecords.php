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

        if (!self::playerHasLocal($map, $player)) {
            $local = LocalRecord::create([
                'Player' => $player->id,
                'Map' => $map->id,
                'Score' => $score
            ]);

            ChatController::messageAll('%s $z$s$%smade a new local record $%s%s', $player->NickName, config('color.primary'), config('color.secondary'), formatScore($score));
        }else{
            $localRecord = $map->locals()->wherePlayer($player->id)->first();

            if ($localRecord && $score < $localRecord->Score) {
                $diff = $localRecord->Score - $score;
                $localRecord->update(['Score' => $score]);
                ChatController::messageAll('%s $z$s$%simproved his/hers local record by $%s%s', $player->NickName, config('color.primary'), config('color.secondary'), formatScore($diff));
            }
        }

        self::displayLocalRecords();
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