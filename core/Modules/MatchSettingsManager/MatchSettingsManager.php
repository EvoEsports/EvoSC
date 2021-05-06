<?php

namespace EvoSC\Modules\MatchSettingsManager;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Classes\Utility;
use EvoSC\Controllers\MatchSettingsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Models\Schedule;
use EvoSC\Modules\QuickButtons\QuickButtons;
use SimpleXMLElement;

class MatchSettingsManager extends Module implements ModuleInterface
{
    private static string $path;
    private static $objects;

    private static array $modeTemplatesManiaplanet = [
        'TimeAttack' => 'timeattack.xml',
        'Team'       => 'team.xml',
        'Rounds'     => 'rounds.xml',
        'Laps'       => 'laps.xml',
        'Cup'        => 'cup.xml',
        'Chase'      => 'chase.xml',
    ];

    private static array $modeTemplatesTrackmania = [
        'TimeAttack' => 'timeattack.xml',
        'Team'       => 'team.xml',
        'Rounds'     => 'rounds.xml',
        'Laps'       => 'laps.xml',
        'Cup'        => 'cup.xml',
    ];

    private static array $gameModesManiaplanet = [
        'TimeAttack' => 'TimeAttack.Script.txt',
        'Rounds'     => 'Rounds.Script.txt',
        'Team'       => 'Team.Script.txt',
        'Cup'        => 'Cup.Script.txt',
        'Laps'       => 'Laps.Script.txt',
        'Chase'      => 'Chase.Script.txt',
    ];

    private static array $gameModesTrackmania = [
        'TimeAttack' => 'Trackmania/TM_TimeAttack_Online.Script.txt',
        'Rounds'     => 'Trackmania/TM_Rounds_Online.Script.txt',
        'Teams'      => 'Trackmania/TM_Teams_Online.Script.txt',
        'Cup'        => 'Trackmania/TM_Cup_Online.Script.txt',
        'Laps'       => 'Trackmania/TM_Laps_Online.Script.txt',
        'Champion'   => 'Trackmania/TM_Champion_Online.Script.txt',
        'Knockout'   => 'Trackmania/TM_Knockout_Online.Script.txt',
    ];

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$path = Server::getMapsDirectory() . '/MatchSettings/';
        self::$objects = collect();

        AccessRight::add('matchsettings_load', 'Load matchsettings.');
        AccessRight::add('matchsettings_edit', 'Edit matchsettings.');

        ChatCommand::add('//msm', [self::class, 'showOverview'], 'Show MatchSettingsManager', 'matchsettings_edit');

        ManiaLinkEvent::add('msm.load', [self::class, 'loadMatchsettings'], 'matchsettings_load');
        ManiaLinkEvent::add('msm.load_and_skip', [self::class, 'loadMatchsettingsAndSkip'], 'matchsettings_load');
        ManiaLinkEvent::add('msm.overview', [self::class, 'showOverview'], 'matchsettings_load');
        ManiaLinkEvent::add('msm.create', [self::class, 'showCreateMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.edit', [self::class, 'showEditMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.edit_maps', [self::class, 'showEditMatchsettingsMaps'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.edit_folders', [self::class, 'showEditMatchsettingsFolders'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.duplicate', [self::class, 'duplicateMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.delete', [self::class, 'deleteMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.new', [self::class, 'createNewMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.update', [self::class, 'updateMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.save_maps', [self::class, 'saveMaps'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.save_folders', [self::class, 'saveFolders'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.schedule', [self::class, 'scheduleMatchSettings'], 'matchsettings_load');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'MatchSetting Manager', 'msm.overview', 'matchsettings_load');
        }
    }

    /**
     * @param Player $player
     * @param $timeStamp
     * @param $matchsettingsFile
     */
    public static function scheduleMatchSettings(Player $player, $timeStamp, $matchsettingsFile)
    {
        Schedule::maniaLinkEvent($player, 'Load ' . $matchsettingsFile, $timeStamp, 'msm.load_and_skip', serverPlayer(), $matchsettingsFile);
    }

    /**
     * @param Player $player
     * @param $data
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function saveMaps(Player $player, $data)
    {
        $matchsettings = $data->matchsettings;

        $enabledMapIds = collect($data)->map(function ($value, $key) {
            if (preg_match('/^map_.+/', $key) && $value == '1') {
                return preg_replace('/^map_/', '', $key);
            }

            return null;
        })->filter()->values();

        $file = mapsDir("MatchSettings/$matchsettings.txt");

        $settings = new SimpleXMLElement(File::get($file));
        unset($settings->map);

        foreach (Map::whereIn('id', $enabledMapIds)->get() as $map) {
            $entry = $settings->addChild('map');
            $entry->addChild('file', $map->filename);
            $entry->addChild('ident', $map->uid);
        }

        File::put($file, Utility::simpleXmlPrettyPrint($settings));

        successMessage('MatchSettings ', secondary($matchsettings), ' saved.')->send($player);
        self::showEditMatchsettingsMaps($player, $matchsettings);
    }

    /**
     * @param Player $player
     * @param $data
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function saveFolders(Player $player, $data)
    {
        $matchsettings = $data->matchsettings;

        $enabledFolders = collect($data)->map(function ($value, $key) {
            if (preg_match('/^folder_.+/', $key) && $value == '1') {
                return preg_replace('/^folder_/', '', $key);
            }
            return null;
        })->filter()->values();

        $file = mapsDir("MatchSettings/$matchsettings.txt");

        $settings = new SimpleXMLElement(File::get($file));
        unset($settings->map);

        $test = Map::whereIn('folder', $enabledFolders)->get();

        foreach ($test as $map) {
            $entry = $settings->addChild('map');
            $entry->addChild('file', $map->filename);
            $entry->addChild('ident', $map->uid);
        }

        File::put($file, Utility::simpleXmlPrettyPrint($settings));

        successMessage('MatchSettings ', secondary($matchsettings), ' saved.')->send($player);
        self::showEditMatchsettingsFolders($player, $matchsettings);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showOverview(Player $player)
    {
        $matchsettings = self::getMatchsettings()->values();

        Template::show($player, 'MatchSettingsManager.overview', compact('matchsettings'));
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showCreateMatchsettings(Player $player)
    {
        if (isManiaPlanet()) {
            $modes = collect(self::$modeTemplatesManiaplanet)->keys();
        } else {
            $modes = collect(self::$modeTemplatesTrackmania)->keys();
        }

        Template::show($player, 'MatchSettingsManager.create', compact('modes'));

    }

    /**
     * @param Player $player
     * @param string $name
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showEditMatchsettings(Player $player, string $name)
    {
        $file = Server::getMapsDirectory() . 'MatchSettings/' . $name . '.txt';
        $data = File::get($file);
        $nodes = collect();
        $xml = new SimpleXMLElement($data);

        foreach ($xml as $node) {
            if ($node->getName() != 'map') {
                if (count($node) > 0) {
                    $nodeName = $node->getName();
                    $items = collect();

                    foreach ($node as $item) {
                        if ($nodeName == 'mode_script_settings' || $nodeName == 'script_settings') {
                            $items->put('' . $item['name'], '' . $item['value']);
                        } else {
                            $items->put($item->getName(), '' . $item[0]);
                        }
                    }

                    $nodes->put($nodeName, $items);
                } else {
                    $nodes->put($node->getName(), '' . $node[0]);
                }
            }
        }

        @Template::show($player, 'MatchSettingsManager.edit-settings', compact('name', 'nodes'));
    }

    /**
     * @param Player $player
     * @param string $name
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showEditMatchsettingsMaps(Player $player, string $name)
    {
        $file = Server::getMapsDirectory() . 'MatchSettings/' . $name . '.txt';
        $data = File::get($file);
        $enabledMapUids = collect();
        $xml = new SimpleXMLElement($data);

        foreach ($xml as $node) {
            if ($node->getName() == 'map') {
                $enabledMapUids->push($node->ident);
            }
        }

        $mapChunks = Map::all()->sortByDesc('enabled')->chunk(19);

        Template::show($player, 'MatchSettingsManager.edit-maps', compact('name', 'mapChunks', 'enabledMapUids'));
    }

    /**
     * @param Player $player
     * @param string $name
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showEditMatchsettingsFolders(Player $player, string $name)
    {
        $file = Server::getMapsDirectory() . 'MatchSettings/' . $name . '.txt';
        $data = File::get($file);
        $enabledFolders = collect();
        $maps = collect();
        $xml = new SimpleXMLElement($data);

        foreach ($xml as $node) {
            if ($node->getName() == 'map') {
                $value = (string)$node->ident;
                $maps->push($value);
            }
        }

        $folders = Map::distinct()->get(['folder']);

        foreach ($folders as $folder) {
            $uid_mapper = function ($item) {return $item->uid;};
            $count = Map::whereFolder($folder->folder)->get()->toBase()->map($uid_mapper)->diff($maps->toBase())->count();
            if($count == 0) {
                $enabledFolders->push($folder->folder);
            }
        }

        $folderChunks = $folders->chunk(19);

        Template::show($player, 'MatchSettingsManager.edit-folders', compact('name', 'folderChunks', 'enabledFolders'));
    }

    /**
     * @param Player $player
     * @param string $oldFilename
     * @param string $filename
     * @param mixed ...$settings
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function updateMatchsettings(Player $player, string $oldFilename, string $filename, ...$settings)
    {
        $settings = json_decode(implode(',', $settings));
        $filename = trim($filename);

        foreach ($settings as $setting => $value) {
            MatchSettingsController::updateSetting($oldFilename . '.txt', $setting, $value);
            Log::info($player . ' set "' . $setting . '" to "' . $value . '" in "' . $oldFilename . '"');
        }

        if ($oldFilename != $filename) {
            MatchSettingsController::rename($oldFilename . '.txt', $filename . '.txt');
            Log::info($player . ' renamed "' . $oldFilename . '" to "' . $filename . '"');
        }

        self::showOverview($player);
    }

    /**
     * @param Player $player
     * @param string $modeName
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function createNewMatchsettings(Player $player, string $modeName)
    {
        $modeFile = self::$modeTemplatesManiaplanet[$modeName];
        $modeBaseName = str_replace('.xml', '', $modeFile);
        $sourceMatchsettings = __DIR__ . '/MatchSettingsRepo/' . $modeFile;
        $matchsettingsDirectory = Server::getMapsDirectory() . 'MatchSettings/';
        $i = 0;

        do {
            $filename = sprintf('%s_%d.txt', $modeBaseName, $i);
            $i++;
            $targetFile = $matchsettingsDirectory . $filename;
        } while (File::exists($matchsettingsDirectory . $filename));

        if (isManiaPlanet()) {
            $scriptName = self::$gameModesManiaplanet[$modeName];
        } else {
            $scriptName = self::$gameModesTrackmania[$modeName];
        }

        $content = File::get($sourceMatchsettings);
        $content = str_replace('%script_name%', $scriptName, $content);

        File::put($targetFile, $content);
        Log::info($player . ' created new "' . $filename . '" with mode "' . $modeName . '"');

        self::showOverview($player);
    }

    /**
     * @param Player $player
     * @param string $matchsettingsFile
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function duplicateMatchsettings(Player $player, string $matchsettingsFile)
    {
        $file = $matchsettingsFile . '.txt';
        $targetFile = $matchsettingsFile . '_copy.txt';
        $matchsettingsDirectory = Server::getMapsDirectory() . 'MatchSettings/';

        File::copy($matchsettingsDirectory . $file, $matchsettingsDirectory . $targetFile);
        Log::info($player . ' duplicated "' . $file . '" as "' . $targetFile . '"');

        self::showOverview($player);
    }

    /**
     * @param Player $player
     * @param string $matchsettingsFile
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function deleteMatchsettings(Player $player, string $matchsettingsFile)
    {
        if (config('server.default-matchsettings') == $matchsettingsFile . '.txt') {
            warningMessage('Can not delete default match-settings.')->send($player);

            return;
        }

        File::delete(Server::getMapsDirectory() . 'MatchSettings/' . $matchsettingsFile . '.txt');
        Log::warning($player . ' deleted "' . $matchsettingsFile . '"');

        self::showOverview($player);
    }

    /**
     * @param Player $player
     * @param string $matchsettingsFile
     */
    public static function loadMatchsettings(Player $player, string $matchsettingsFile)
    {
        MatchSettingsController::loadMatchSettings(true, $player, $matchsettingsFile . '.txt');
    }

    /**
     * @param Player $player
     * @param string $matchSettingsFile
     */
    public static function loadMatchsettingsAndSkip(Player $player, string $matchSettingsFile)
    {
        self::loadMatchsettings($player, $matchSettingsFile);
        Server::nextMap();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public static function getMatchsettings()
    {
        return File::getDirectoryContents(self::$path, '/\.txt$/')->transform(function (string $file) {
            return preg_replace('/\.txt$/', '', $file);
        });
    }
}