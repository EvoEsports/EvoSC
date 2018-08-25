<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\MatchSettings;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\Player;
use esc\Modules\MapList\MapList;
use Illuminate\Support\Collection;

class MatchSettingsManager
{
    private static $path;
    private static $objects;

    public static function init()
    {
        self::$path = config('server.base') . '/UserData/Maps/MatchSettings/';
        self::$objects = collect();

        ChatController::addCommand('ms', [self::class, 'showMatchSettingsOverview'], 'Show MatchSettingsManager', '//', 'ms.edit');

        ManiaLinkEvent::add('msm.delete', [self::class, 'deleteMatchSetting']);
        ManiaLinkEvent::add('msm.duplicate', [self::class, 'duplicateMatchSettings']);
        ManiaLinkEvent::add('msm.load', [self::class, 'loadMatchSettings']);
        ManiaLinkEvent::add('msm.overview', [self::class, 'showMatchSettingsOverview']);
        ManiaLinkEvent::add('msm.save', [self::class, 'saveMatchSettings']);

        ManiaLinkEvent::add('msm.edit', [self::class, 'editMatchSettings']);
        ManiaLinkEvent::add('msm.edit_mss', [self::class, 'editModeScriptSettings']);
        ManiaLinkEvent::add('msm.edit_maps', [self::class, 'editMaps']);
        ManiaLinkEvent::add('msm.update', [self::class, 'updateMatchSettings']);

        KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        $settings = preg_replace('/\.txt$/', '', MatchSettingsManager::getMatchSettings()->first());
        MatchSettingsManager::editMatchSettings($player, $settings);
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

    public static function editMatchSettings(Player $player, string $matchSettingsFile)
    {
        $content = File::get(self::$path . $matchSettingsFile . '.txt');
        $xml = new \SimpleXMLElement($content);
        $ms = new MatchSettings($xml, uniqid(), $matchSettingsFile . '.txt');

        self::$objects->push($ms);
        self::editMaps($player, $ms->id);
    }

    public static function editModeScriptSettings(Player $player, string $reference)
    {
        $ms = self::getXmlObject($reference);

        $modeScriptSettings = collect();
        foreach ($ms->xml->mode_script_settings->setting as $setting) {
            $modeScriptSettings->push([
                'name' => $setting['name'] . "",
                'value' => $setting['value'] . "",
                'type' => $setting['type'] . "",
            ]);
        }

        $modeScriptSettings = $modeScriptSettings->sortBy('type', SORT_REGULAR, SORT_DESC)->split(2);

        Template::show($player, 'matchsettings-manager.edit-modescript-settings', compact('ms', 'modeScriptSettings'));
    }

    public static function editMaps(Player $player, string $reference)
    {
        $ms = self::getXmlObject($reference);

        $enabledMaps = collect();

        foreach ($ms->xml->map as $map) {
            $enabledMaps->push("$map->ident");
        }

        //Get currently enabled maps
        $enabledMaps = Map::whereIn('uid', $enabledMaps)->get();

        //Get currently disabled maps
        $disabledMaps = Map::all()->diff($enabledMaps);

        //make MS compatible
        $enabledMaps = $enabledMaps->map(function (Map $map) {
            return sprintf('["id"=>"%s", "name"=>"%s", "login"=>"%s", "enabled"=>"%d"]', $map->id, $map->gbx->Name, $map->gbx->AuthorLogin, 1);
        });
        $disabledMaps = $disabledMaps->map(function (Map $map) {
            return sprintf('["id"=>"%s", "name"=>"%s", "login"=>"%s", "enabled"=>"%d"]', $map->id, $map->gbx->Name, $map->gbx->AuthorLogin, 0);
        });

        //Attach disabled at end of enabled maps
        $maps = $enabledMaps->concat($disabledMaps);

        //Make maniascript array
        $mapsArray = '[' . $maps->implode(',') . ']';

        Template::show($player, 'matchsettings-manager.edit-maps', compact('ms', 'maps', 'mapsArray'));
    }

    public static function getXmlObject(string $reference): MatchSettings
    {
        return self::$objects->where('id', $reference)->first();
    }

    public static function updateMatchSettings(Player $player, string $reference, string ...$cmd)
    {
        $matchSettings = self::getXmlObject($reference);
        $matchSettings->handle($player, ...$cmd);
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

        //check for existing copy
        $copyName = $files->map(function (string $file) use ($name) {
            if (preg_match("/$name - Copy \((\d+)\)/", $file, $matches)) {
                return $name . ' - Copy (' . (intval($matches[1]) + 1) . ')';
            }
        })->filter()->last();

        if (!$copyName) {
            //no existing copy, create first
            $copyName = $name . ' - Copy (1)';
        }

        $originalFile = self::$path . $name . '.txt';
        File::put(self::$path . $copyName . '.txt', File::get($originalFile));

        //update the manialink
        self::showMatchSettingsOverview($player);

        Log::logAddLine('MatchSettingsManager', "$player duplicated MatchSettingsFile: $name.txt");
    }
}