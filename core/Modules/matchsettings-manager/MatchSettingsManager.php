<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\MatchSettings;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MatchSettingsManager
{
    public static function init()
    {
        ChatController::addCommand('matchsettings', [self::class, 'showMatchSettingsOverview'], 'Show MatchSettingsManager', '//', 'ms.edit');
    }

    public static function showMatchSettingsOverview(Player $player)
    {
        $settings = self::getMatchSettings();

        Template::show($player, 'matchsettings-manager.overview', compact('settings'));
    }

    public static function getMatchSettings(): Collection
    {
        $path = config('server.base') . '/UserData/Maps/MatchSettings/';
        $files = File::getDirectoryContents($path, '/\.txt$/');

        return $files;
    }

    public static function loadMatchSettings(string $file): ?MatchSettings
    {
        $path = config('server.base') . '/UserData/Maps/MatchSettings/';
        $contents = File::get($path . $file);

        return new MatchSettings($contents);
    }
}