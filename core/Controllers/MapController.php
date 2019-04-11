<?php

namespace esc\Controllers;


use Carbon\Carbon;
use esc\Classes\ChatCommand;
use esc\Classes\Config;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\MapQueue;
use esc\Models\Player;
use esc\Modules\KeyBinds;
use esc\Modules\MxMapDetails;
use esc\Modules\QuickButtons;
use Maniaplanet\DedicatedServer\Xmlrpc\FileException;
use mysql_xdevapi\Exception;

/**
 * Class MapController
 *
 * @package esc\Controllers
 */
class MapController implements ControllerInterface
{
    /**
     * @var Map
     */
    private static $currentMap;

    /**
     * @var Map
     */
    private static $nextMap;

    /**
     * @var Carbon
     */
    private static $mapStart;

    private static $mapsPath;
    private static $addedTime = 0;
    private static $timeLimit;

    /**
     * Initialize MapController
     */
    public static function init()
    {
        self::$mapsPath  = Server::getMapsDirectory();
        self::$timeLimit = self::getTimeLimitFromMatchSettings();

        self::loadMaps();

        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);
        Hook::add('EndMatch', [self::class, 'endMatch']);

        AccessRight::createIfNonExistent('map_skip', 'Skip map instantly.');
        AccessRight::createIfNonExistent('map_add', 'Add map permanently.');
        AccessRight::createIfNonExistent('map_delete', 'Delete map (and all records) permanently.');
        AccessRight::createIfNonExistent('map_disable', 'Disable map.');
        AccessRight::createIfNonExistent('map_replay', 'Force a replay.');
        AccessRight::createIfNonExistent('map_reset', 'Reset round.');
        AccessRight::createIfNonExistent('matchsettings_load', 'Load matchsettings.');
        AccessRight::createIfNonExistent('matchsettings_edit', 'Edit matchsettings.');
        AccessRight::createIfNonExistent('time', 'Change the countdown time.');

        ChatCommand::add('//skip', [MapController::class, 'skip'], 'Skips map instantly', 'map_skip');
        ChatCommand::add('//settings', [MapController::class, 'settings'], 'Load match settings', 'matchsettings_load');
        ChatCommand::add('//res', [MapController::class, 'forceReplay'], 'Queue map for replay', 'map_replay');
        ChatCommand::add('//addtime', [MapController::class, 'addTimeManually'], 'Add time in minutes to the countdown (you can add negative time or decimals like 0.5 for 30s)', 'time');

        ManiaLinkEvent::add('map.skip', [MapController::class, 'skip'], 'map_skip');
        ManiaLinkEvent::add('map.replay', [MapController::class, 'forceReplay'], 'map_replay');
        ManiaLinkEvent::add('map.reset', [MapController::class, 'resetRound'], 'map_reset');

        KeyBinds::add('add_one_minute', 'Add one minute to the countdown.', [self::class, 'addMinute'], 'Q', 'time');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Skip Map', 'map.skip', 'map_skip');
            // QuickButtons::addButton('', 'Replay Map', 'map.replay', 'map_replay');
            // QuickButtons::addButton('', 'Reset Map', 'map.reset', 'map_reset');
        }
    }

    /**
     * Load the time limit from the default match-settings.
     *
     * @return int
     */
    private static function getTimeLimitFromMatchSettings(): int
    {
        $file = config('server.default-matchsettings');

        if ($file) {
            $matchSettings = File::get(self::$mapsPath . 'MatchSettings/' . $file);
            $xml           = new \SimpleXMLElement($matchSettings);
            foreach ($xml->mode_script_settings->children() as $child) {
                if ($child->attributes()['name'] == 'S_TimeLimit') {
                    return intval($child->attributes()['value']);
                }
            }
        }

        return 600;
    }

    /**
     * Reset time on round end for example
     */
    public static function resetTime()
    {
        self::$addedTime = 0;
        self::setTimelimit(self::$timeLimit);
    }

    /**
     * Add time to the counter
     *
     * @param int $seconds
     */
    public static function addTime(int $seconds = 600)
    {
        self::$addedTime += $seconds;
        $newTimeLimit    = self::$timeLimit + self::$addedTime;
        self::setTimelimit($newTimeLimit);

        Hook::fire('TimeLimitUpdated', $newTimeLimit);
    }

    /**
     * Add one minute to the countdown.
     *
     * @param \esc\Models\Player $player
     */
    public static function addMinute(Player $player)
    {
        self::addTime(60);
    }

    /**
     * Chat-command: Add time
     *
     * @param \esc\Models\Player $player
     * @param                    $cmd
     * @param float              $amount
     */
    public static function addTimeManually(Player $player, $cmd, float $amount)
    {
        self::addTime($amount * 60.0);
        Log::logAddLine('MapController', $player . ' added ' . $amount . ' minutes');
    }

    /**
     * Set a new timelimit in seconds.
     *
     * @param int $seconds
     */
    public static function setTimelimit(int $seconds)
    {
        $settings                = Server::getModeScriptSettings();
        $settings['S_TimeLimit'] = $seconds;
        Server::setModeScriptSettings($settings);
    }

    /*
     * Hook: BeginMap
     */
    public static function beginMap(Map $map)
    {
        if (self::$nextMap && self::$nextMap->uid != $map->uid) {
            Log::logAddLine('ERROR', sprintf('Expected map %s, got %s', self::$nextMap->uid, $map->uid));
            exit(3);
        }

        QueueController::dropMapSilent($map->uid);

        self::$nextMap    = null;
        self::$currentMap = $map;
        self::$mapStart   = now();

        Map::where('id', '!=', $map->id)->increment('cooldown');

        $map->update([
            'last_played' => now(),
            'cooldown'    => 0,
            'plays'       => $map->plays + 1,
        ]);

        MxMapDetails::loadMxDetails($map);

        //TODO: move to player controller
        Player::where('Score', '>', 0)->update([
            'Score' => 0,
        ]);

        QueueController::preCacheNextMap();
    }

    /**
     * Hook: BeginMatch
     */
    public static function beginMatch()
    {
        self::resetTime();
        self::addTime(0);
    }

    /**
     * Hook: EndMatch
     */
    public static function endMatch()
    {
        $request = MapQueue::getFirst();

        $nextMap       = Map::where('uid', Server::getNextMapInfo()->uId)->first();
        self::$nextMap = $nextMap;

        if ($request) {
            $chatMessage = chatMessage('Upcoming map ', secondary($request->map), ' requested by ', $request->player);
        } else {
            $chatMessage = chatMessage('Upcoming map ', secondary($nextMap));
        }

        $chatMessage->setIcon('')->sendAll();
    }

    /**
     * Get the currently played map.
     *
     * @return Map
     */
    public static function getCurrentMap(): Map
    {
        if (!self::$currentMap) {
            Log::error('Current map is not set. Exiting...', true);
            exit(2);
        }

        return self::$currentMap;
    }

    /**
     * Remove a map
     *
     * @param \esc\Models\Player $player
     * @param \esc\Models\Map    $map
     */
    public static function deleteMap(Player $player, Map $map)
    {
        try {
            Server::removeMap($map->filename);
        } catch (\Exception $e) {
            Log::logAddLine('MapController', 'Delete map: ' . $e->getMessage());
        }

        $map->locals()->delete();
        $deleted = File::delete(self::$mapsPath . $map->filename);

        if ($deleted) {
            try {
                $map->delete();
            } catch (\Exception $e) {
                Log::logAddLine('MapController', 'Failed to remove map "' . $map->uid . '" from database: ' . $e->getMessage(), isVerbose());
            }

            try {
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
            } catch (\Exception $e) {
                Log::logAddLine('MapController', 'Failed to save match-settings: ' . $e->getMessage());
            }

            Hook::fire('MapPoolUpdated');

            warningMessage($player, ' deleted map ', $map)->sendAll();
        } else {
            Log::logAddLine('MapController', 'Failed to delete map "' . $map->filename . '": ' . $e->getMessage(), isVerbose());
        }
    }

    /**
     * Disable a map and remove it from the current selection.
     *
     * @param \esc\Models\Player $player
     * @param \esc\Models\Map    $map
     */
    public static function disableMap(Player $player, Map $map)
    {
        try {
            Server::removeMap($map->filename);
            infoMessage($player, ' disabled map ', secondary($map))->sendAll();
        } catch (\Exception $e) {
            Log::error($e);
        }

        $map->update(['enabled' => false]);
        Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));

        Hook::fire('MapPoolUpdated');
    }

    /**
     * Ends the match and goes to the next round
     */
    public static function goToNextMap()
    {
        Server::nextMap();
    }

    /**
     * Admins skip method
     *
     * @param Player $player
     */
    public static function skip(Player $player = null)
    {
        if ($player) {
            infoMessage($player, ' skips map')->sendAll();
        }

        MapController::goToNextMap();
    }

    /**
     * Force replay a round at end of match
     *
     * @param Player $player
     */
    public static function forceReplay(Player $player)
    {
        $currentMap = self::getCurrentMap();
        QueueController::queueMap($player, $currentMap);
    }

    /**
     * Get gbx-information for a map by filename.
     *
     * @param $filename
     *
     * @return string
     */
    public static function getGbxInformation($filename): string
    {
        return shell_exec(Server::GameDataDirectory() . '/../ManiaPlanetServer /parsegbx="' . $filename . '"');
    }

    /**
     * Loads maps from server directory
     */
    public static function loadMaps()
    {
        Log::logAddLine('MapController', 'Loading maps...');

        //Get loaded matchsettings maps
        $maps = collect(Server::getMapList());

        //get array with the uids
        $enabledMapsuids = $maps->pluck('uId');

        foreach ($maps as $mapInfo) {
            $mapFile = self::$mapsPath . $mapInfo->fileName;

            if (!File::exists($mapFile)) {
                throw new Exception("File $mapFile not found.");
                continue;
            }

            $map = Map::whereUid($mapInfo->uId)->first();

            if (!$map) {
                if (Map::whereFilename($mapInfo->fileName)->exists()) {
                    Map::whereFilename($mapInfo->fileName)->delete();
                }

                if (Player::where('Login', $mapInfo->author)->exists()) {
                    $authorId = Player::where('Login', $mapInfo->author)->first()->id;
                } else {
                    $authorId = Player::insertGetId([
                        'Login'    => $mapInfo->author,
                        'NickName' => $mapInfo->author,
                    ]);
                }

                $gbxInfo = self::getGbxInformation(self::$mapsPath . $mapInfo->fileName);
                $map     = Map::updateOrCreate([
                    'author'   => $authorId,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'filename' => $mapInfo->fileName,
                    'uid'      => json_decode($gbxInfo)->MapUid,
                ]);
            }

            if (!$map->checksum) {
                $map->update([
                    'checksum' => md5_file($mapFile),
                ]);
            }

            if (!$map->gbx) {
                $gbxInfo = self::getGbxInformation(self::$mapsPath . $mapInfo->fileName);
                $map->update([
                    'gbx' => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'uid' => json_decode($gbxInfo)->MapUid,
                ]);
            }

            if (isVerbose()) {
                printf("Loaded: %60s -> %s\n", $mapInfo->fileName, stripAll($map->gbx->Name));
            } else {
                echo ".";
            }
        }

        echo "\n";

        //Disable maps
        Map::whereNotIn('uid', $enabledMapsuids)
           ->update(['enabled' => false]);

        //Enable loaded maps
        Map::whereIn('uid', $enabledMapsuids)
           ->update(['enabled' => true]);
    }

    /**
     * @return int
     */
    public static function getTimeLimit(): int
    {
        return self::$timeLimit + self::$addedTime;
    }

    /**
     * @return int
     */
    public static function getOriginalTimeLimit(): int
    {
        return self::$timeLimit;
    }

    /**
     * @return int
     */
    public static function getAddedTime(): int
    {
        return self::$addedTime;
    }

    /**
     * Reset the round.
     *
     * @param \esc\Models\Player $player
     */
    public static function resetRound(Player $player)
    {
        Server::restartMap();
    }

    /**
     * Get the maps directory-path.
     *
     * @return string
     */
    public static function getMapsPath(): string
    {
        return self::$mapsPath;
    }

    /**
     * Get the round-start-time.
     *
     * @return \Carbon\Carbon
     */
    public static function getMapStart(): Carbon
    {
        return self::$mapStart;
    }
}