<?php

namespace esc\Modules\PBRecords;

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Dedi;
use esc\Models\LocalRecord;
use esc\Models\Player;

class PBRecords
{
    private static $targets;

    public function __construct()
    {
        Hook::add('PlayerConnect', [PBRecords::class, 'playerConnect']);
        Hook::add('EndMatch', [PBRecords::class, 'endMatch']);
        Hook::add('BeginMatch', [PBRecords::class, 'beginMatch']);
        Hook::add('PlayerLocal', [PBRecords::class, 'playerMadeRecord']);
        Hook::add('PlayerDedi', [PBRecords::class, 'playerMadeRecord']);

        ChatController::addCommand('target', [PBRecords::class, 'setTarget'], 'Use /target local|dedi|wr #id to load CPs of record to bottom widget', '/');

        self::$targets = collect([]);
    }

    public static function playerMadeRecord(Player $player, $record)
    {
        self::playerConnect($player);
    }

    public static function playerConnect(Player $player)
    {
        self::updateTarget($player);
        Template::show($player, 'pb-records.pb-cp-records');
    }

    public static function endMatch(...$args)
    {
        self::$targets = collect([]);
    }

    public static function beginMatch(...$args)
    {
        onlinePlayers()->each([self::class, 'playerConnect']);
    }

    public static function updateTarget(Player $player)
    {
        $target = self::getTarget($player);

        if ($target) {
            $targetString = 'unknown';

            if ($target instanceof LocalRecord) {
                $targetString = sprintf('%d. Local  %s$z  %s', $target->Rank, $target->player->NickName ?? $target->player->Login, formatScore($target->Score));
            } elseif ($target instanceof Dedi) {
                $targetString = sprintf('%d. Dedi  %s$z  %s', $target->Rank, $target->player->NickName ?? $target->player->Login, formatScore($target->Score));
            }

            $checkpoints = collect(explode(',', $target->Checkpoints));
            $timesHash = md5($checkpoints->toJson());

            Template::show($player, 'pb-records.set-times', compact('checkpoints', 'targetString', 'timesHash'));
        }
    }

    public static function setTarget(Player $player, $cmd, $dediOrLocal = null, $recordId = null)
    {
        if (!$dediOrLocal) {
            ChatController::message($player, info('You must specify ') . secondary('local') . info(' or ') . secondary('dedi') . info(' or ') . secondary('wr') . info(' as first and the id of the record as second'));

            return;
        }

        $currentTarget = self::$targets->where('player', $player)->first();

        $map = MapController::getCurrentMap();

        switch ($dediOrLocal) {
            case 'wr':
                $record = $map->dedis()->whereRank(1)->first();
                break;

            case 'dedi':
                $record = $map->dedis()->whereRank($recordId ?? 1)->first();
                break;

            case 'local':
                $record = $map->locals()->whereRank($recordId ?? 1)->first();
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

        self::updateTarget($player);
    }

    private static function getTarget(Player $player)
    {
        $target = self::$targets->where('player', $player);

        if ($target->isNotEmpty()) {
            return $target->first()->record;
        }

        $map = MapController::getCurrentMap();

        $local = $map->locals()
                     ->wherePlayer($player->id)
                     ->get()
                     ->first();

        $dedi = $map->dedis()
                    ->wherePlayer($player->id)
                    ->get()
                    ->first();

        if ($local && $dedi) {
            if ($dedi->Score <= $local->Score) {
                return $dedi;
            }

            return $local;
        }

        if ($dedi) {
            return $dedi;
        }

        if ($local) {
            return $local;
        }

        $dedi = $map->dedis()->orderByDesc('Score')->first();

        if(!$dedi){
            return $map->locals()->orderByDesc('Score')->first();
        }

        return $dedi;
    }
}