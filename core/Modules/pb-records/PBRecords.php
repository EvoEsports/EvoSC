<?php

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Models\Player;
use Illuminate\Support\Collection;

class PBRecords
{
    private static $checkpoints;

    public function __construct()
    {
        Template::add('pbrecords', File::get(__DIR__ . '/Templates/pb-records.latte.xml'));

        Hook::add('PlayerFinish', 'PBRecords::playerFinish');
        Hook::add('PlayerCheckpoint', 'PBRecords::playerCheckpoint');
        Hook::add('BeginMatch', 'PBRecords::beginMatch');
        Hook::add('EndMatch', 'PBRecords::endMatch');

        PBRecords::$checkpoints = collect([]);

        foreach(onlinePlayers() as $player){
            PBRecords::$checkpoints->put($player->id, collect([]));
        }
    }

    public static function beginMatch(...$args)
    {
        foreach (onlinePlayers() as $player) {
            self::showWidget($player);
        }
    }

    public static function endMatch(...$args)
    {
        Template::hideAll('pbrecords');
    }

    public static function showWidget(Player $player, $cpId = null)
    {
        $checkpoints = self::$checkpoints->get($player->id);
        $pbRecords = self::getPbRecordTimes($player);

        if ($pbRecords && $checkpoints) {
            Template::show($player, 'pbrecords', ['times' => $pbRecords, 'current' => $checkpoints->toArray()]);
        }else{
            Template::hide($player, 'pbrecords');
        }
    }

    public static function playerFinish(Player $player, int $score)
    {
        if ($score > 0) {
            return;
        }

        self::$checkpoints->put($player->id, collect([]));
        self::showWidget($player);
    }

    public static function playerCheckpoint(Player $player, int $score, int $curLap, int $cpId)
    {
        if(!self::$checkpoints->get($player->id)){
            self::$checkpoints->put($player->id, collect([]));
        }

        self::$checkpoints->get($player->id)->put($cpId, $score);
        self::showWidget($player, $cpId);
    }

    private static function getPbRecordTimes(Player $player)
    {
        $map = MapController::getCurrentMap();

        $pb = $map->locals()->wherePlayer($player->id)->get()->first();

        $dedi = $map->dedis()->wherePlayer($player->id)->get()->first();

        if($dedi && $dedi->Score < $pb->Score && $dedi->Checkpoints){
            $pb = $dedi;
        }

        if ($pb) {
            return explode(',', $pb->Checkpoints);
        }

        return null;
    }
}