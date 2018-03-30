<?php

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Player;

class PBRecords
{
    private static $checkpoints;
    private static $targets;

    public function __construct()
    {
        Template::add('pbrecords', File::get(__DIR__ . '/Templates/pb-records.latte.xml'));

        Hook::add('PlayerCheckpoint', 'PBRecords::playerCheckpoint');
        Hook::add('PlayerStartCountdown', 'PBRecords::playerStartCountdown');
        Hook::add('EndMatch', 'PBRecords::endMatch');

        ChatController::addCommand('target', 'PBRecords::setTarget', 'Use /target local|dedi|wr #id to load CPs of record to bottom widget', '/');

        self::$targets = collect([]);
        PBRecords::$checkpoints = collect([]);

        foreach (onlinePlayers() as $player) {
            PBRecords::$checkpoints->put($player->id, collect([]));
        }
    }

    public static function playerStartCountdown(Player $player)
    {
        self::$checkpoints->put($player->id, collect([]));
        self::showWidget($player);
    }

    public static function endMatch(...$args)
    {
        Template::hideAll('pbrecords');
    }

    public static function showWidget(Player $player, $cpId = null)
    {
        $checkpoints = self::$checkpoints->get($player->id);
        $target = self::getTarget($player);

        $targetString = 'unknown';

        if ($target instanceof LocalRecord) {
            $targetString = sprintf('%d. Local  %s$z  %s', $target->Rank, $target->player->NickName ?? $target->player->Login, formatScore($target->Score));
        } elseif ($target instanceof Dedi) {
            $targetString = sprintf('%d. Dedi  %s$z  %s', $target->Rank, $target->player->NickName ?? $target->player->Login, formatScore($target->Score));
        }

        $recordCpTimes = explode(',', $target->Checkpoints);

        if ($target && $checkpoints) {
            Template::show($player, 'pbrecords', ['times' => $recordCpTimes, 'current' => $checkpoints->toArray(), 'target' => $targetString]);
        } else {
            Template::hide($player, 'pbrecords');
        }
    }

    public static function playerCheckpoint(Player $player, int $score, int $curLap, int $cpId)
    {
        if (!self::$checkpoints->get($player->id)) {
            self::$checkpoints->put($player->id, collect([]));
        }

        self::$checkpoints->get($player->id)->put($cpId, $score);
        self::showWidget($player, $cpId);
    }

    public static function setTarget(Player $player, $cmd, $dediOrLocal = null, $recordId = null)
    {
        if (!$dediOrLocal) {
            ChatController::message($player, info('You must specify ') . secondary('local') . info(' or ') . secondary('dedi') . info(' or ') . secondary('wr') . info(' as first and the id of the record as second'));
            return;
        }

        $currentTarget = self::$targets->where('player', $player)->first();

        $map = \esc\Controllers\MapController::getCurrentMap();

        switch ($dediOrLocal) {
            case 'wr':
                $record = $map->dedis()->whereRank(1)->get()->first();
                break;

            case 'dedi':
                $record = $map->dedis()->whereRank($recordId ?? 1)->get()->first();
                break;

            case 'local':
                $record = $map->locals()->whereRank($recordId ?? 1)->get()->first();
                break;

            default:
                ChatController::message($player, 'You must specify "local" or "dedi" as first parameter');
                $record = null;
                break;
        }

        if (!$record) {
            ChatController::message($player, 'Unknown record selected');
            return;
        }

        if (!isset($record->Checkpoints)) {
            ChatController::message($player, 'Record has no saved checkpoints');
            return;
        }

        if ($currentTarget != null) {
            $currentTarget->record = $record;
        } else {
            $currentTarget = collect([]);
            $currentTarget->player = $player;
            $currentTarget->record = $record;
            self::$targets->push($currentTarget);
        }

        ChatController::message($player, 'New checkpoints target: ', $record);

        self::showWidget($player);
    }

    private static function getTarget(Player $player)
    {
        $target = self::$targets->where('player', $player);
        if ($target->isNotEmpty()) {
            return $target->first()->record;
        }

        $map = MapController::getCurrentMap();

        $local = $map->locals()->wherePlayer($player->id)->get()->first();
        $dedi = $map->dedis()->wherePlayer($player->id)->get()->first();

        if ($local && $dedi) {
            if ($dedi->Score <= $local->Score) {
                return $dedi;
            }

            return $local;
        }

        if ($dedi) {
            return $dedi;
        }

        return $local;
    }
}