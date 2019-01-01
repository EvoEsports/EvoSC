<?php

namespace esc\Modules;

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\AccessRight;
use esc\Models\Player;
use Illuminate\Support\Collection;

class CPRecords
{
    public function __construct()
    {
        AccessRight::createIfNonExistent('cpr.reset', 'Reset top checkpoints');

        Hook::add('ShowScores', [CPRecords::class, 'clearCheckpoints']);
        Hook::add('PlayerCheckpoint', [CPRecords::class, 'playerCheckpoint']);
        Hook::add('PlayerConnect', [CPRecords::class, 'playerConnect']);

        ManiaLinkEvent::add('cpr.reset', [self::class, 'reset'], 'cpr.reset');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Reset Checkpoints', 'cpr.reset', 'map.reset');
        }
    }

    public static function playerConnect(Player $player)
    {
        Template::show($player, 'cp-records.widget');
    }
}