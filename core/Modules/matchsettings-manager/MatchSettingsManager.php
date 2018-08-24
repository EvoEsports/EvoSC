<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use esc\Modules\MapList\MapList;
use Illuminate\Support\Collection;

class MatchSettingsManager
{
    private static $path;

    public static function init()
    {
        self::$path = config('server.base') . '/UserData/Maps/MatchSettings/';

        ChatController::addCommand('ms', [self::class, 'showMatchSettingsOverview'], 'Show MatchSettingsManager', '//', 'ms.edit');

        ManiaLinkEvent::add('msm.delete', [self::class, 'deleteMatchSetting']);
        ManiaLinkEvent::add('msm.duplicate', [self::class, 'duplicateMatchSettings']);
        ManiaLinkEvent::add('msm.load', [self::class, 'loadMatchSettings']);

        KeyController::createBind('Y', [self::class, 'showMatchSettingsOverview']);
    }

    public static function showMatchSettingsOverview(Player $player)
    {
        TemplateController::loadTemplates();

        $settings = self::getMatchSettings()->map(function (String $file) {
            return preg_replace('/\.txt$/', '', $file);
        });

        Template::show($player, 'matchsettings-manager.overview', compact('settings'));
    }

    public static function getMatchSettings(): Collection
    {
        $path = config('server.base') . '/UserData/Maps/MatchSettings/';
        $files = File::getDirectoryContents($path, '/\.txt$/');

        return $files;
    }

    public static function deleteMatchSetting(Player $player, string $matchSettingsFile)
    {
        $file = self::$path . $matchSettingsFile . '.txt';
        File::delete($file);
        self::showMatchSettingsOverview($player);

        Log::logAddLine('MatchSettingsManager', "$player deleted MatchSettingsFile: $matchSettingsFile");
    }

    public static function loadMatchSettings(Player $player, string $matchSettingsFile)
    {
        $file = 'MatchSettings/' . $matchSettingsFile . '.txt';
        Server::loadMatchSettings($file);

        //Update maps
        onlinePlayers()->each([MapList::class, 'sendManialink']);

        ChatController::messageAll($player->group, ' ', $player->NickName, ' loads new settings ', secondary($matchSettingsFile));

        Log::logAddLine('MatchSettingsManager', "$player loads MatchSettings: $matchSettingsFile");
    }

    public static function duplicateMatchSettings(Player $player, string $name)
    {
        $files = self::getMatchSettings();

        $file = $files->map(function (string $file) use ($name) {
            if (preg_match("/$name - Copy \((\d+)\)/", $file, $matches)) {
                return $name . ' - Copy (' . (intval($matches[1]) + 1) . ')';
            }
        })->filter()->last();

        if (!$file) {
            $file = $name . ' - Copy (1)';
        }

        $originalFile = self::$path . $name . '.txt';
        File::put(self::$path . $file . '.txt', File::get($originalFile));
        self::showMatchSettingsOverview($player);

        Log::logAddLine('MatchSettingsManager', "$player duplicated MatchSettingsFile: $matchSettingsFile");
    }
}