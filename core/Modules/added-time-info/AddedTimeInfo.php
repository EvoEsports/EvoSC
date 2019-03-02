<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\Player;

class AddedTimeInfo
{
    public function __construct()
    {
        Hook::add('EndMatch', [self::class, 'endMatch']);
        Hook::add('TimeLimitUpdated', [self::class, 'timeLimitUpdated']);
    }

    public static function timeLimitUpdated($timeLimitInSeconds)
    {
        $addedMinutes = floor(MapController::getAddedTime() / 60);
        Template::showAll('added-time-info.meter', compact('addedMinutes'));
    }
}