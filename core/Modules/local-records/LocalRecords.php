<?php

use esc\classes\Database;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\ManiaBuilder;
use esc\classes\Timer;
use esc\controllers\ChatController;
use esc\controllers\MapController;
use esc\ManiaLink\Elements\Label;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Database\Schema\Blueprint;
use Maniaplanet\DedicatedServer\Xmlrpc\ParseException;

class LocalRecords
{
    public function __construct()
    {
        $this->createTables();

        include_once 'Models/LocalRecord.php';

        Hook::add('PlayerFinish', 'LocalRecords::playerFinish');
        Hook::add('PlayerConnect', 'LocalRecords::playerConnect');
        Hook::add('BeginMap', 'LocalRecords::beginMap');
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
            LocalRecord::create([
                'Player' => $player->id,
                'Map' => $map->id,
                'Score' => $score
            ]);

            $message = sprintf('%s made a new local record (%s).', $player->NickName, Timer::formatScore($score));
        }else{
            $localRecord = $map->locals()->wherePlayer($player->id)->first();

            if ($localRecord && $score < $localRecord->Score) {
                $diff = $localRecord->Score - $score;
                $localRecord->update(['Score' => $score]);
                $message = sprintf('%s improved his local record (-%s).', $player->NickName, Timer::formatScore($diff));
            }
        }

        self::displayLocalRecords();

        if(isset($message)){
            Log::info($message);
            ChatController::messageAll($message);
        }
    }

    public static function playerConnect()
    {
        self::displayLocalRecords();
    }

    public static function beginMap()
    {
        self::displayLocalRecords();
    }

    public static function displayLocalRecords()
    {
        $map = MapController::getCurrentMap();

        $builder = new ManiaBuilder('LocalRecords', ManiaBuilder::STICK_LEFT, 56, 90, 120, 0.55, ['padding' => 3, 'bgcolor' => '0009']);

        $label = new Label("LocalRecords", ['width' => 70, 'textsize' => 5, 'height' => 12]);
        $builder->addRow($label);

        $i = 1;
        foreach ($map->locals()->orderBy('Score')->get() as $localRecord) {
            $index = new Label("$i.", ['width' => 8, 'textsize' => 3, 'valign' => 'center', 'halign' => 'right']);
            $score = new Label($localRecord->getScore(), ['width' => '22', 'textsize' => 3, 'valign' => 'center', 'padding-left' => 3, 'textcolor' => 'FFFF']);
            $nick = new Label($localRecord->getPlayer()->NickName, ['textsize' => 3, 'valign' => 'center', 'padding-left' => 2]);
            $builder->addRow($index, $score, $nick);
            $i++;
        }

        try {
            $builder->sendToAll();
        } catch (ParseException $e) {
            Log::error($e);
        }
    }
}