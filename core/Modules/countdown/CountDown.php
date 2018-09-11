<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class CountDown
{
    static $timeLimit;

    public function __construct()
    {
        self::$timeLimit = MapController::getTimeLimit();

        Hook::add('PlayerConnect', [self::class, 'showCountdown']);
        Hook::add('TimeLimitUpdated', [self::class, 'timeLimitUpdated']);
        Hook::add('BeginMap', [self::class, 'beginMap']);

        KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::showCountdown($player);
    }

    public static function beginMap()
    {
        $map = MapController::getCurrentMap();

        $bestTime = $map->gbx->AuthorTime;

        if ($map->dedis()->count() > 0) {
            $bestDedi = $map->dedis()->orderByDesc('Score')->first();

            if ($bestDedi->Score < $bestTime) {
                $bestTime = $bestDedi->Score;
            }
        }

        Template::showAll('countdown.widget', compact('bestTime'));
    }

    public function timeLimitUpdated(int $timeLimitInSeconds)
    {
        self::$timeLimit = $timeLimitInSeconds;
        Template::showAll('countdown.update-timelimit', ['timeLimit' => $timeLimitInSeconds]);
    }

    public static function showCountdown(Player $player)
    {
        $map = MapController::getCurrentMap();

        $bestTime = $map->gbx->AuthorTime;

        if ($map->dedis()->count() > 0) {
            $bestDedi = $map->dedis()->orderByDesc('Score')->first();

            if ($bestDedi->Score < $bestTime) {
                $bestTime = $bestDedi->Score;
            }
        }

        Template::show($player, 'countdown.widget', compact('bestTime'));
    }
}