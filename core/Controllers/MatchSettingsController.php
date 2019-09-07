<?php


namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Timer;
use esc\Interfaces\ControllerInterface;
use esc\Models\Map;
use esc\Models\Player;
use esc\Modules\QuickButtons;
use Exception;
use Illuminate\Support\Collection;
use SimpleXMLElement;

class MatchSettingsController implements ControllerInterface
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

        if (!File::exists(self::getPath(self::$currentMatchSettingsFile))) {
            Log::error('MatchSettings "'.self::getPath(self::$currentMatchSettingsFile).'" not found.');
            exit(1);
        }
    }

    public static function loadMatchSettings(Player $player, string $matchSettingsFile)
    {
        Server::loadMatchSettings('MatchSettings/'.$matchSettingsFile);
        infoMessage($player, ' loads matchsettings ', secondary($matchSettingsFile))->sendAll();
        Log::info($player.' loads matchsettings '.$matchSettingsFile);

        $mode = self::getModeScript($matchSettingsFile);

        ChatCommand::removeAll();
        Timer::destroyAll();
        ManiaLinkEvent::removeAll();
        if (config('quick-buttons.enabled')) {
            QuickButtons::removeAll();
        }

        ControllerController::loadControllers($mode);
        ModuleController::startModules($mode);
    }

    public static function getModeScript(string $matchSettings): string
    {
        $file = self::getPath($matchSettings);
        $settings = new SimpleXMLElement(File::get($file));

        return $settings->gameinfos->script_name[0];
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
            Log::error("Failed to add map \"$map\" to \"$matchSettings\"");
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
            Log::error("Failed to shuffle map-list: " . $e->getMessage());
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
                Log::write("Removing map by uid ($uid) from $matchSettings.");
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
                Log::write("Removing map by filename ($filename) from $matchSettings.");
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

    public static function updateSetting(string $matchSettingsFile, string $setting, $value)
    {
        $file = self::getPath($matchSettingsFile);
        $settings = new SimpleXMLElement(File::get($file));

        $root = explode('.', $setting)[0];

        if ($root == 'script_settings') {
            foreach ($settings->script_settings->setting as $setting_) {
                if($setting_['name'] == explode('.', $setting)[1]){
                    $setting_['value'] = $value;
                }
            }
        } else if ($root == 'mode_script_settings') {
            foreach ($settings->mode_script_settings->setting as $setting_) {
                if($setting_['name'] == explode('.', $setting)[1]){
                    $setting_['value'] = $value;
                }
            }
        } else {
            $nodePath = collect(explode('.', $setting))->transform(function ($node) {
                return "{$node}";
            })->implode('->');

            eval('$settings->'.$nodePath.' = $value;');
        }


        $domDocument = new \DOMDocument("1.0");
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = true;
        $domDocument->loadXML($settings->asXML());
        File::put($file, $domDocument->saveXML());
    }

    public static function rename(string $oldName, string $newName)
    {
        File::rename(self::getPath($oldName), self::getPath($newName));
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

    /**
     * @param  string  $mode
     */
    public static function start($mode)
    {
        ChatCommand::add('//shuffle', [self::class, 'shuffleCurrentMapListCommand'], 'Shuffle the current map-pool.',
            'map_add');
    }
}