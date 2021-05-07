<?php


namespace EvoSC\Controllers;


use DOMDocument;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Classes\Timer;
use EvoSC\Classes\Utility;
use EvoSC\Commands\EscRun;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\QuickButtons\QuickButtons;
use Exception;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Structures\Map;
use SimpleXMLElement;

class MatchSettingsController implements ControllerInterface
{
    /**
     * @var string
     */
    private static string $currentMatchSettingsFile;

    private static string $lastMatchSettingsModification;

    /**
     *
     */
    public static function init()
    {
        self::$currentMatchSettingsFile = (string)config('server.default-matchsettings');

        if (!File::exists(self::getPath(self::$currentMatchSettingsFile))) {
            Log::error('MatchSettings "' . self::getPath(self::$currentMatchSettingsFile) . '" not found.');
            exit(1);
        }
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        ChatCommand::add('//shuffle', [self::class, 'shuffleCurrentMapListCommand'], 'Shuffle the current map-pool.', 'map_add');

        self::$lastMatchSettingsModification = filemtime(self::getPath(self::getCurrentMatchSettingsFile()));

//        Timer::create('detect_match_settings_changes', [self::class, 'detectMatchSettingsChanges'], '5s', true);
    }

    /**
     * @param bool $rebootClasses
     * @param Player|null $player
     * @param string|null $matchSettingsFile
     */
    public static function loadMatchSettings(bool $rebootClasses = false, Player $player = null, string $matchSettingsFile = null)
    {
        if ($player) {
            infoMessage($player, ' loads matchsettings ', secondary($matchSettingsFile))->sendAll();
            Log::info($player . ' loads matchsettings ' . $matchSettingsFile);
        } else {
            Log::info('Automatically loading matchsettings ' . $matchSettingsFile);
        }

        if (is_null($matchSettingsFile)) {
            $matchSettingsFile = self::getCurrentMatchSettingsFile();
        }

        Server::loadMatchSettings('MatchSettings/' . $matchSettingsFile);
        self::$currentMatchSettingsFile = $matchSettingsFile;
        self::$lastMatchSettingsModification = filemtime(self::getPath($matchSettingsFile));

        if ($rebootClasses) {
            ModeController::rebootModules();
        }

        QueueController::dropAllMaps();
        MapController::loadMaps();
        Hook::fire('MapPoolUpdated');
        Hook::fire('MatchSettingsLoaded', $matchSettingsFile);
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
     * @param string $matchSettings
     * @param string $filename
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
     * @param string $matchSettings
     * @param string $uid
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
     * @param string $matchSettings
     * @param string $filename
     * @param string $uid
     */
    public static function addMap(string $matchSettings, string $filename, string $uid)
    {
        $file = self::getPath($matchSettings);
        $settings = new SimpleXMLElement(File::get($file));

        $node = $settings->addChild('map');
        $node->addChild('file', $filename);
        $node->addChild('ident', $uid);

        try {
            self::saveMatchSettings($file, $settings);
        } catch (Exception $e) {
            Log::errorWithCause("Failed to add map \"$filename\" to \"$matchSettings\"", $e);
        }
    }

    /**
     * @param Player $player
     */
    public static function shuffleCurrentMapListCommand(Player $player)
    {
        $file = self::getPath(self::$currentMatchSettingsFile);
        $settings = new SimpleXMLElement(File::get($file));
        unset($settings->map);

        $maps = DB::table('maps')
            ->select(['filename', 'uid'])
            ->where('enabled', '=', 1)
            ->inRandomOrder()
            ->get();

        foreach ($maps as $map) {
            $entry = $settings->addChild('map');
            $entry->addChild('file', $map->filename);
            $entry->addChild('ident', $map->uid);
        }

        File::put($file, Utility::simpleXmlPrettyPrint($settings));
        Server::loadMatchSettings('MatchSettings/' . self::$currentMatchSettingsFile);
        infoMessage($player, ' shuffled the map list.')->sendAll();
    }

    /**
     * @param string $matchSettings
     * @param string $uid
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
        self::updateMatchSettingsModificationTime();
    }

    /**
     * @param string $matchSettings
     * @param string $filename
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
        self::updateMatchSettingsModificationTime();
    }

    /**
     * @param string $matchSettings
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
     * @param string $filename
     *
     * @return bool
     */
    public static function filenameExistsInCurrentMatchSettings(string $filename): bool
    {
        return self::filenameExists(self::$currentMatchSettingsFile, $filename);
    }

    /**
     * @param string $uid
     *
     * @return bool
     */
    public static function uidExistsInCurrentMatchSettings(string $uid): bool
    {
        return self::uidExists(self::$currentMatchSettingsFile, $uid);
    }

    /**
     * @param string $uid
     */
    public static function removeByUidFromCurrentMatchSettings(string $uid)
    {
        self::removeByUid(self::$currentMatchSettingsFile, $uid);
    }

    /**
     * @param string $filename
     */
    public static function removeByFilenameFromCurrentMatchSettings(string $filename)
    {
        self::removeByFilename(self::$currentMatchSettingsFile, $filename);
    }

    /**
     * @param string $filename
     * @param string $uid
     */
    public static function addMapToCurrentMatchSettings(string $filename, string $uid)
    {
        self::addMap(self::$currentMatchSettingsFile, $filename, $uid);
        self::updateMatchSettingsModificationTime();
    }

    /**
     * @param string $key
     * @return int
     */
    public static function getValueFromCurrentMatchSettings(string $key)
    {
        $file = self::getCurrentMatchSettingsFile();
        $matchSettings = File::get(MapController::getMapsPath('MatchSettings/' . $file));
        $xml = new SimpleXMLElement($matchSettings);
        $node = null;

        if (isset($xml->mode_script_settings)) {
            $node = $xml->mode_script_settings;
        } else {
            $node = $xml->script_settings;
        }

        if ($node) {
            foreach ($node->children() as $child) {
                if ($child->attributes()['name'] == $key) {
                    return intval($child->attributes()['value']);
                }
            }
        }

        return -1;
    }

    public static function updateSetting(string $matchSettingsFile, string $setting, $value)
    {
        $file = self::getPath($matchSettingsFile);
        $settings = new SimpleXMLElement(File::get($file));

        $root = explode('.', $setting)[0];

        if ($root == 'script_settings') {
            foreach ($settings->script_settings->setting as $setting_) {
                if ($setting_['name'] == explode('.', $setting)[1]) {
                    $setting_['value'] = $value;
                }
            }
        } else {
            if ($root == 'mode_script_settings') {
                foreach ($settings->mode_script_settings->setting as $setting_) {
                    if ($setting_['name'] == explode('.', $setting)[1]) {
                        $setting_['value'] = $value;
                    }
                }
            } else {
                $nodePath = collect(explode('.', $setting))->transform(function ($node) {
                    return "{$node}";
                })->implode('->');

                eval('$settings->' . $nodePath . ' = $value;');
            }
        }


        $domDocument = new DOMDocument("1.0");
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
     * @param string $matchSettingsFile
     *
     * @return string
     */
    private static function getPath(string $matchSettingsFile)
    {
        return Server::getMapsDirectory() . 'MatchSettings' . DIRECTORY_SEPARATOR . $matchSettingsFile;
    }

    /**
     * @param string $file
     * @param SimpleXMLElement $matchSettings
     */
    private static function saveMatchSettings(string $file, SimpleXMLElement $matchSettings)
    {
        $domDocument = new DOMDocument("1.0");
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

    public static function detectMatchSettingsChanges()
    {
        clearstatcache();
        if (self::$lastMatchSettingsModification != filemtime(self::getPath(self::getCurrentMatchSettingsFile()))) {
            self::updateMatchSettingsModificationTime();
            infoMessage('MatchSettings was updated by external source, reloading.')->sendAll();
            self::loadMatchSettings();
        }
    }

    public static function updateMatchSettingsModificationTime()
    {
        self::$lastMatchSettingsModification = filemtime(self::getPath(self::getCurrentMatchSettingsFile()));
    }
}
