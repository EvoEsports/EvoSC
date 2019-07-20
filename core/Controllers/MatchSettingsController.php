<?php


namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Models\Map;
use esc\Models\Player;
use Exception;
use Illuminate\Support\Collection;
use SimpleXMLElement;

class MatchSettingsController
{
    /**
     * @var string
     */
    private static $currentMatchSettingsFile;

    /**
     *
     */
    public static function init()
    {
        self::$currentMatchSettingsFile = config('server.default-matchsettings');

        ChatCommand::add('//shuffle', [self::class, 'shuffleCurrentMapListCommand'], 'Shuffle the current map-pool.',
            'map_add');

        if (!File::exists(self::getPath(self::$currentMatchSettingsFile))) {
            Log::error('MatchSettings "'.self::getPath(self::$currentMatchSettingsFile).'" not found.');
            exit(1);
        }
    }

    /**
     * @return string
     */
    public static function getCurrentMatchSettingsFile(): string
    {
        return self::$currentMatchSettingsFile;
    }

    /**
     * @param  string  $matchSettings
     * @param  string  $filename
     *
     * @return bool
     */
    public static function filenameExists(string $matchSettings, string $filename): bool
    {
        foreach (self::getMapFilenamesFrom($matchSettings) as $mapInfo) {
            if ($mapInfo->file == $filename) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string  $matchSettings
     * @param  string  $uid
     *
     * @return bool
     */
    public static function uidExists(string $matchSettings, string $uid): bool
    {
        foreach (self::getMapFilenamesFrom($matchSettings) as $mapInfo) {
            if ($mapInfo->ident == $uid) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string  $matchSettings
     * @param  Map  $map
     */
    public static function addMap(string $matchSettings, Map $map)
    {
        $file = self::getPath($matchSettings);
        $settings = new SimpleXMLElement(File::get($file));

        $node = $settings->addChild('map');
        $node->addChild('file', $map->filename);
        $node->addChild('ident', $map->uid);

        try {
            self::saveMatchSettings($file, $settings);
        } catch (Exception $e) {
            Log::write('MatchSettingsController', "Failed to add map ($map) to $matchSettings.");
        }
    }

    /**
     * @param  Player  $player
     */
    public static function shuffleCurrentMapListCommand(Player $player)
    {
        infoMessage('The map-list gets shuffled after the map finished.')->sendAdmin();

        Hook::add('Maniaplanet.EndMap_Start', function () use ($player) {
            MatchSettingsController::shuffleCurrentMapList();
            infoMessage($player, ' shuffled the map-list.')->sendAll();
            Server::loadMatchSettings(MatchSettingsController::getPath(MatchSettingsController::$currentMatchSettingsFile));
        }, true);
    }

    /**
     *
     */
    public static function shuffleCurrentMapList()
    {
        $maps = collect();
        $file = self::getPath(self::$currentMatchSettingsFile);
        $settings = new SimpleXMLElement(File::get($file));

        foreach ($settings->map as $mapInfo) {
            $maps->push([
                'file' => (string) $mapInfo->file,
                'ident' => (string) $mapInfo->ident,
            ]);
        }

        unset($settings->map);
        unset($settings->startindex);
        $settings->addChild('startindex', 0);

        $maps->shuffle()->each(function ($map) use ($settings) {
            $mapNode = $settings->addChild('map');
            $mapNode->addChild('file', $map['file']);
            $mapNode->addChild('ident', $map['ident']);
        });

        try {
            self::saveMatchSettings($file, $settings);
        } catch (Exception $e) {
            Log::write('MatchSettingsController', "Failed to shuffle map-list.");
        }
    }

    /**
     * @param  string  $matchSettings
     * @param  string  $uid
     */
    public static function removeByUid(string $matchSettings, string $uid)
    {
        $file = self::getPath($matchSettings);
        $settings = new SimpleXMLElement(File::get($file));

        foreach ($settings->map as $mapInfo) {
            if ($mapInfo->ident == $uid) {
                Log::write('MatchSettingsController', "Removing map by uid ($uid) from $matchSettings.");
                unset($mapInfo[0]);
                break;
            }
        }

        self::saveMatchSettings($file, $settings);
    }

    /**
     * @param  string  $matchSettings
     * @param  string  $filename
     */
    public static function removeByFilename(string $matchSettings, string $filename)
    {
        $file = self::getPath($matchSettings);
        $settings = new SimpleXMLElement(File::get($file));

        foreach ($settings->map as $mapInfo) {
            if ($mapInfo->file == $filename) {
                Log::write('MatchSettingsController', "Removing map by filename ($filename) from $matchSettings.");
                unset($mapInfo[0]);
                break;
            }
        }

        self::saveMatchSettings($file, $settings);
    }

    /**
     * @param  string  $matchSettings
     *
     * @return Collection
     */
    public static function getMapFilenamesFrom(string $matchSettings): Collection
    {
        $mapInfos = collect();
        $file = self::getPath($matchSettings);

        foreach ((new SimpleXMLElement(File::get($file)))->map as $mapInfo) {
            $mapInfos->push($mapInfo);
        }

        return $mapInfos;
    }

    /**
     * @return Collection
     */
    public static function getMapFilenamesFromCurrentMatchSettings(): Collection
    {
        return self::getMapFilenamesFrom(self::$currentMatchSettingsFile);
    }

    /**
     * @param  string  $filename
     *
     * @return bool
     */
    public static function filenameExistsInCurrentMatchSettings(string $filename): bool
    {
        return self::filenameExists(self::$currentMatchSettingsFile, $filename);
    }

    /**
     * @param  string  $uid
     *
     * @return bool
     */
    public static function uidExistsInCurrentMatchSettings(string $uid): bool
    {
        return self::uidExists(self::$currentMatchSettingsFile, $uid);
    }

    /**
     * @param  string  $uid
     */
    public static function removeByUidFromCurrentMatchSettings(string $uid)
    {
        self::removeByUid(self::$currentMatchSettingsFile, $uid);
    }

    /**
     * @param  string  $filename
     */
    public static function removeByFilenameFromCurrentMatchSettings(string $filename)
    {
        self::removeByFilename(self::$currentMatchSettingsFile, $filename);
    }

    /**
     * @param  Map  $map
     */
    public static function addMapToCurrentMatchSettings(Map $map)
    {
        self::addMap(self::$currentMatchSettingsFile, $map);
    }

    /**
     * @param  string  $matchSettingsFile
     *
     * @return string
     */
    private static function getPath(string $matchSettingsFile)
    {
        return Server::getMapsDirectory().config('server.matchsettings-directory').DIRECTORY_SEPARATOR.$matchSettingsFile;
    }

    /**
     * @param  string  $file
     * @param  SimpleXMLElement  $matchSettings
     */
    private static function saveMatchSettings(string $file, SimpleXMLElement $matchSettings)
    {
        $domDocument = new \DOMDocument("1.0");
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = true;
        $domDocument->loadXML($matchSettings->asXML());
        File::put($file, $domDocument->saveXML());
    }

    public static function setMapIdent(string $matchSettingsFile, string $filename, string $uid)
    {
        $file = self::getPath($matchSettingsFile);
        $settings = new SimpleXMLElement(File::get($file));

        foreach ($settings->map as $mapInfo) {
            if ($mapInfo->file == $filename) {
                $mapInfo->ident = $uid;
            }
        }

        self::saveMatchSettings($file, $settings);
    }
}