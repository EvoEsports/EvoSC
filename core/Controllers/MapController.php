<?php

namespace esc\Controllers;

use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\MapFavorite;
use esc\Models\MapQueue;
use esc\Models\Player;
use esc\Modules\Dedimania;
use esc\Modules\LocalRecords;
use esc\Modules\MxMapDetails;
use esc\Modules\QuickButtons;
use Exception;
use GBXChallMapFetcher;
use stdClass;

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
        global $_skipMapCheck;

        if (config('server.map-cooldown') == 0) {
            Log::error('Map cooldown must be 1 or greater.');
            exit(3); //Configuration error
        }

        if (!File::dirExists(cacheDir('gbx'))) {
            File::makeDir(cacheDir('gbx'));
        }

        self::$mapsPath = Server::getMapsDirectory();

        if (!$_skipMapCheck) {
            self::loadMaps();
        }

        AccessRight::createIfMissing('map_skip', 'Skip map instantly.');
        AccessRight::createIfMissing('map_add', 'Add map permanently.');
        AccessRight::createIfMissing('map_delete', 'Delete map (and all records) permanently.');
        AccessRight::createIfMissing('map_disable', 'Disable map.');
        AccessRight::createIfMissing('map_replay', 'Force a replay.');
        AccessRight::createIfMissing('map_reset', 'Reset round.');
        AccessRight::createIfMissing('matchsettings_load', 'Load matchsettings.');
        AccessRight::createIfMissing('matchsettings_edit', 'Edit matchsettings.');
        AccessRight::createIfMissing('time', 'Change the countdown time.');
    }

    /**
     * @param Map $map
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    public static function beginMap(Map $map)
    {
        self::$nextMap = null;
        self::$currentMap = $map;

        Map::where('cooldown', '<=', config('server.map-cooldown'))
            ->increment('cooldown');

        $map->update([
            'last_played' => now(),
            'cooldown' => 0,
            'plays' => $map->plays + 1,
        ]);

        MxMapDetails::loadMxDetails($map);
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
                    Log::write('Adding map to selection failed: ' . $e->getMessage());
                }
            }

            QueueController::dropMapSilent($request->map->uid);
            $chosen = Server::chooseNextMap($request->map->filename);

            if (!$chosen) {
                Log::write('Failed to chooseNextMap ' . $request->map->filename);
            }

            self::$nextMap = $request->map;
        } else {
            self::$nextMap = Map::where('uid', $mapUid)->first();
        }
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
            exit(4); //Runtime error
        }

        return self::$currentMap;
    }

    /**
     * Remove a map
     *
     * @param Player $player
     * @param Map $map
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

        DB::table(LocalRecords::TABLE)->where('Map', '=', $map->id)->delete();
        DB::table(Dedimania::TABLE)->where('Map', '=', $map->id)->delete();
        MapFavorite::whereMapId($map->id)->delete();
        $deleted = File::delete(self::$mapsPath . $map->filename);

        if ($deleted) {
            try {
                $map->delete();
                Log::write($player . '(' . $player->Login . ') deleted map ' . $map . ' [' . $map->uid . ']');
            } catch (Exception $e) {
                Log::write('Failed to remove map "' . $map->uid . '" from database: ' . $e->getMessage(), isVerbose());
            }

            MatchSettingsController::removeByFilenameFromCurrentMatchSettings($map->filename);

            Hook::fire('MapPoolUpdated');

            warningMessage($player, ' deleted map ', $map)->sendAll();

            QueueController::preCacheNextMap();
        } else {
            Log::write('Failed to delete map "' . $map->filename . '": ' . $e->getMessage(), isVerbose());
        }
    }

    /**
     * Disable a map and remove it from the current selection.
     *
     * @param Player $player
     * @param Map $map
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
        Log::write($player . '(' . $player->Login . ') disabled map ' . $map . ' [' . $map->uid . ']');

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
        $map = MapController::getCurrentMap();

        if ($player) {
            infoMessage($player, ' skips map')->sendAll();
        }

        Log::write($map . ' [' . $map->uid . '] was skipped by ' . ($player ? "$player" : 'the server') . '.');
        $map->increment('skipped');

        MapController::goToNextMap();
    }

    /**
     * @param Player $player
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * @param bool $asString
     * @return string|stdClass|null
     */
    public static function getGbxInformation($filename, bool $asString = true)
    {
        $mapFile = Server::GameDataDirectory() . 'Maps' . DIRECTORY_SEPARATOR . $filename;
        $data = new GBXChallMapFetcher(true);

        try {
            $data->processFile($mapFile);
        } catch (Exception $e) {
            Log::write($e->getMessage(), isVerbose());

            return self::getGbxInformationByExecutable($filename);
        }

        $gbx = new stdClass();
        $gbx->CheckpointsPerLaps = $data->nbChecks;
        $gbx->NbLaps = $data->nbLaps;
        $gbx->DisplayCost = $data->cost;
        $gbx->LightmapVersion = $data->lightmap;
        $gbx->AuthorTime = $data->authorTime;
        $gbx->GoldTime = $data->goldTime;
        $gbx->SilverTime = $data->silverTime;
        $gbx->BronzeTime = $data->bronzeTime;
        $gbx->IsValidated = $data->validated;
        $gbx->PasswordProtected = $data->password != '';
        $gbx->MapStyle = $data->mapStyle;
        $gbx->MapType = $data->mapType;
        $gbx->Mod = $data->modName;
        $gbx->Decoration = $data->mood;
        $gbx->Environment = $data->envir;
        $gbx->PlayerModel = 'Unassigned';
        $gbx->MapUid = $data->uid;
        $gbx->Comment = $data->comment;
        $gbx->TitleId = Server::getVersion()->titleId;
        $gbx->AuthorLogin = $data->authorLogin;
        $gbx->AuthorNick = $data->authorNick;
        $gbx->Name = $data->name;
        $gbx->ClassName = 'CGameCtnChallenge';
        $gbx->ClassId = '03043000';

        Log::write('Get GBX information: ' . $filename, isVerbose());

        if (!$gbx->Name) {
            $gbx = self::getGbxInformationByExecutable($filename);
        }

        if ($asString) {
            return json_encode($gbx);
        }

        return $gbx;
    }

    private static function getGbxInformationByExecutable($filename)
    {
        $mps = Server::GameDataDirectory() . (isWindows() ? DIRECTORY_SEPARATOR : '') . '..' . DIRECTORY_SEPARATOR . 'ManiaPlanetServer';
        $mapFile = Server::GameDataDirectory() . 'Maps' . DIRECTORY_SEPARATOR . $filename;
        $cmd = $mps . sprintf(' /parsegbx="%s"', $mapFile);

        return json_decode(shell_exec($cmd));
    }

    /**
     * Loads maps from server directory
     */
    public static function loadMaps()
    {
        Log::write('Loading maps...');

        DB::table('maps')
            ->where('enabled', '=', 1)
            ->update(['enabled' => 0]);

        foreach (Server::getMapList() as $map) {
            /** @var $map \Maniaplanet\DedicatedServer\Structures\Map */
            Log::info('Loading ' . $map->fileName, isVerbose());

            DB::table('maps')->updateOrInsert([
                'uid' => $map->uId
            ], [
                'author' => self::createOrGetAuthor($map->author),
                'filename' => $map->fileName,
                'name' => $map->name,
                'environment' => $map->environnement,
                'enabled' => 1,
                'cooldown' => config('server.map-cooldown', 10)
            ]);

            echo '.';
        }

        echo "\n";
    }

    public static function createOrGetAuthor(string $login)
    {
        $author = DB::table('players')->where('Login', '=', $login)->first();

        if ($author) {
            $authorId = $author->id;
        } else {
            $authorId = DB::table('players')->insertGetId([
                'Login' => $map->author,
                'NickName' => $map->author
            ]);

            try {
                $gbx = self::getGbxInformation($map->fileName);
                DB::table('players')->where('Login', '=', $map->author)->update([
                    'NickName' => $gbx->AuthorNick
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to load GBX information for: ' . $map->fileName, isVerbose());
            }
        }

        return $authorId;
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

    /**
     * @return Map
     */
    public static function getNextMap(): stdClass
    {
        return DB::table('maps')
            ->where('uid', '=', Server::getNextMapInfo()->uId)
            ->first();
    }

    public static function resetRound(Player $player)
    {
        infoMessage($player, ' resets the round.')->sendAll();

        Server::restartMap();
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('Maniaplanet.EndRound_Start', [self::class, 'endMatch']);

        ChatCommand::add('//skip', [self::class, 'skip'], 'Skips map instantly', 'map_skip');
        ChatCommand::add('//settings', [self::class, 'settings'], 'Load match settings', 'matchsettings_load');
        ChatCommand::add('//res', [self::class, 'forceReplay'], 'Queue map for replay', 'map_replay');

        ManiaLinkEvent::add('map.skip', [self::class, 'skip'], 'map_skip');
        ManiaLinkEvent::add('map.replay', [self::class, 'forceReplay'], 'map_replay');
        ManiaLinkEvent::add('map.reset', [self::class, 'resetRound'], 'map_reset');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Skip Map', 'map.skip', 'map_skip');
            QuickButtons::addButton('', 'Reset Map', 'map.reset', 'map_reset');
            // QuickButtons::addButton('', 'Replay Map', 'map.replay', 'map_replay');
        }
    }
}