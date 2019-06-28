<?php

namespace esc\Controllers;

use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Dedi;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\MapFavorite;
use esc\Models\MapQueue;
use esc\Models\Player;
use esc\Modules\MxMapDetails;
use esc\Modules\NextMap;
use esc\Modules\QuickButtons;
use Exception;
use Illuminate\Contracts\Queue\Queue;

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
     * @var string
     */
    private static $mapsPath;

    /**
     * Initialize MapController
     */
    public static function init()
    {
        self::$mapsPath = Server::getMapsDirectory();
        self::loadMaps();

        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('Maniaplanet.EndRound_Start', [self::class, 'endMatch']);

        AccessRight::createIfNonExistent('map_skip', 'Skip map instantly.');
        AccessRight::createIfNonExistent('map_add', 'Add map permanently.');
        AccessRight::createIfNonExistent('map_delete', 'Delete map (and all records) permanently.');
        AccessRight::createIfNonExistent('map_disable', 'Disable map.');
        AccessRight::createIfNonExistent('map_replay', 'Force a replay.');
        AccessRight::createIfNonExistent('map_reset', 'Reset round.');
        AccessRight::createIfNonExistent('matchsettings_load', 'Load matchsettings.');
        AccessRight::createIfNonExistent('matchsettings_edit', 'Edit matchsettings.');
        AccessRight::createIfNonExistent('time', 'Change the countdown time.');

        ChatCommand::add('//skip', [self::class, 'skip'], 'Skips map instantly', 'map_skip');
        ChatCommand::add('//settings', [self::class, 'settings'], 'Load match settings', 'matchsettings_load');
        ChatCommand::add('//res', [self::class, 'forceReplay'], 'Queue map for replay', 'map_replay');

        ManiaLinkEvent::add('map.skip', [self::class, 'skip'], 'map_skip');
        ManiaLinkEvent::add('map.replay', [self::class, 'forceReplay'], 'map_replay');
        ManiaLinkEvent::add('map.reset', [self::class, 'resetRound'], 'map_reset');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Skip Map', 'map.skip', 'map_skip');
            // QuickButtons::addButton('', 'Replay Map', 'map.replay', 'map_replay');
            // QuickButtons::addButton('', 'Reset Map', 'map.reset', 'map_reset');
        }
    }


    /**
     * @param Map $map
     *
     * @throws Exception
     */
    public static function beginMap(Map $map)
    {
        self::$nextMap = null;
        self::$currentMap = $map;

        Map::where('id', '!=', $map->id)
            ->where('cooldown', '<=', config('server.map-cooldown'))
            ->increment('cooldown');

        $map->update([
            'last_played' => now(),
            'cooldown'    => 0,
            'plays'       => $map->plays + 1,
        ]);

        MxMapDetails::loadMxDetails($map);

        //TODO: move to player controller
        Player::where('Score', '>', 0)
            ->update([
                'Score' => 0,
            ]);
    }

    /**
     * Hook: EndMatch
     */
    public static function endMatch()
    {
        $request = MapQueue::getFirst();

        $mapUid = Server::getNextMapInfo()->uId;

        if ($request) {
            if (!Server::isFilenameInSelection($request->map->filename)) {
                try {
                    Server::addMap($request->map->filename);
                } catch (Exception $e) {
                    Log::logAddLine('MxDownload', 'Adding map to selection failed: ' . $e->getMessage());
                }
            }

            QueueController::dropMapSilent($request->map->uid);
            $chosen = Server::chooseNextMap($request->map->filename);

            if (!$chosen) {
                Log::logAddLine('MapController', 'Failed to chooseNextMap ' . $request->map->filename);
            }

            $chatMessage = chatMessage('Upcoming map ', secondary($request->map), ' requested by ', $request->player);
            self::$nextMap = $request->map;
        } else {
            self::$nextMap = Map::where('uid', $mapUid)->first();
            $chatMessage = chatMessage('Upcoming map ', secondary(self::$nextMap));
        }

        NextMap::showNextMap(self::$nextMap);

        $chatMessage->setIcon('')
            ->sendAll();
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
     * @param Player $player
     * @param Map    $map
     */
    public static function deleteMap(Player $player, Map $map)
    {
        if (Server::isFilenameInSelection($map->filename)) {
            try {
                Server::removeMap($map->filename);
            } catch (Exception $e) {
                Log::error($e);
            }
        }

        $map->locals()
            ->delete();
        $map->dedis()
            ->delete();
        MapFavorite::whereMapId($map->id)
            ->delete();
        $deleted = File::delete(self::$mapsPath . $map->filename);

        if ($deleted) {
            try {
                $map->delete();
                Log::logAddLine('MapController',
                    $player . '(' . $player->Login . ') deleted map ' . $map . ' [' . $map->uid . ']');
            } catch (Exception $e) {
                Log::logAddLine('MapController',
                    'Failed to remove map "' . $map->uid . '" from database: ' . $e->getMessage(), isVerbose());
            }

            MatchSettingsController::removeByFilenameFromCurrentMatchSettings($map->filename);

            Hook::fire('MapPoolUpdated');

            warningMessage($player, ' deleted map ', $map)->sendAll();

            QueueController::preCacheNextMap();
        } else {
            Log::logAddLine('MapController', 'Failed to delete map "' . $map->filename . '": ' . $e->getMessage(),
                isVerbose());
        }
    }

    /**
     * Disable a map and remove it from the current selection.
     *
     * @param Player $player
     * @param Map    $map
     */
    public static function disableMap(Player $player, Map $map)
    {
        if (Server::isFilenameInSelection($map->filename)) {
            try {
                Server::removeMap($map->filename);
            } catch (Exception $e) {
                Log::error($e);
            }
        }

        infoMessage($player, ' disabled map ', secondary($map))->sendAll();
        Log::logAddLine('MapController',
            $player . '(' . $player->Login . ') disabled map ' . $map . ' [' . $map->uid . ']');

        $map->update(['enabled' => 0]);
        MatchSettingsController::removeByFilenameFromCurrentMatchSettings($map->filename);

        Hook::fire('MapPoolUpdated');

        QueueController::preCacheNextMap();
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
        $mps = Server::GameDataDirectory() . (isWindows() ? DIRECTORY_SEPARATOR : '') . '..' . DIRECTORY_SEPARATOR . 'ManiaPlanetServer';
        $mapFile = Server::GameDataDirectory() . 'Maps' . DIRECTORY_SEPARATOR . $filename;
        $cmd = $mps . sprintf(' /parsegbx="%s"', $mapFile);

        Log::logAddLine('MapController', 'Get GBX information: ' . $cmd);

        return shell_exec($cmd);
    }

    /**
     * Loads maps from server directory
     */
    public static function loadMaps()
    {
        Log::logAddLine('MapController', 'Loading maps...');

        //Get loaded matchsettings maps
        $maps = MatchSettingsController::getMapFilenamesFromCurrentMatchSettings();

        foreach ($maps as $mapInfo) {
            $filename = $mapInfo->file;
            $uid = $mapInfo->ident;
            $mapFile = self::$mapsPath . $filename;

            if (!File::exists($mapFile)) {
                Log::error("File $mapFile not found.");

                if (Map::whereFilename($filename)
                    ->exists()) {
                    Map::whereFilename($filename)
                        ->update(['enabled' => 0]);
                }

                continue;
            }

            if (!$uid) {
                Log::logAddLine('MapController', 'Missing ident in match-settings for map: ' . $filename);
                $gbxJson = self::getGbxInformation($filename);
                $gbx = json_decode($gbxJson);
                $uid = $gbx->MapUid;
            }

            if (Map::whereFilename($filename)
                ->exists()) {
                $map = Map::whereFilename($filename)
                    ->first();

                if ($map->uid != $uid) {
                    Log::logAddLine('MapController', 'UID changed for map: ' . $map, isVerbose());

                    LocalRecord::whereMap($map->id)
                        ->delete();
                    Dedi::whereMap($map->id)
                        ->delete();
                    MapFavorite::whereMapId($map->id)
                        ->delete();
                    MapQueue::whereMapUid($map->uid)
                        ->delete();

                    $map->update([
                        'gbx'             => self::getGbxInformation($filename),
                        'uid'             => $uid,
                        'mx_details'      => null,
                        'mx_world_record' => null,
                    ]);
                }
            } else {
                if (Map::whereUid($uid)
                    ->exists()) {
                    $map = Map::whereUid($uid)
                        ->first();

                    Log::logAddLine('MapController', "Filename changed for map: (" . $map->filename . " -> $filename)",
                        isVerbose());

                    $map->update([
                        'filename' => $filename,
                    ]);
                } else {
                    $gbxJson = self::getGbxInformation($filename);
                    $gbx = json_decode($gbxJson);
                    $authorLogin = $gbx->AuthorLogin;

                    if (Player::where('Login', $authorLogin)
                        ->exists()) {
                        $authorId = Player::find($authorLogin)->id;
                    } else {
                        $authorId = Player::insertGetId([
                            'Login'    => $authorLogin,
                            'NickName' => $authorLogin,
                        ]);
                    }

                    $map = Map::create([
                        'author'   => $authorId,
                        'filename' => $filename,
                        'gbx'      => self::getGbxInformation($filename),
                        'uid'      => $uid,
                    ]);
                }
            }

            $map->update(['enabled' => 1]);

            if (isVerbose()) {
                printf("Loaded: %60s -> %s\n", $mapInfo->fileName, stripAll($map->gbx->Name));
            } else {
                echo ".";
            }
        }

        echo "\n";

        //get array with the uids
        $enabledMapsuids = $maps->pluck('uId');

        //Enable loaded maps
        Map::whereIn('uid', $enabledMapsuids)
            ->update(['enabled' => true]);

        //Disable maps
        Map::whereNotIn('uid', $enabledMapsuids)
            ->orWhere('gbx', null)
            ->update(['enabled' => false]);
    }

    /**
     * Reset the round.
     *
     * @param Player $player
     */
    public static function resetRound(Player $player)
    {
        Server::restartMap();
    }

    /**
     * Get the maps directory-path, optionally add the filename at the end.
     *
     * @param string|null $fileName
     *
     * @return string
     */
    public static function getMapsPath(string $fileName = null): string
    {
        if ($fileName) {
            return self::$mapsPath . $fileName;
        }

        return self::$mapsPath;
    }

    /**
     * @param Map $currentMap
     */
    public static function setCurrentMap(Map $currentMap): void
    {
        self::$currentMap = $currentMap;
    }
}