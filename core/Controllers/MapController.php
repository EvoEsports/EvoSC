<?php

namespace EvoSC\Controllers;

use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\MPS_Map;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Map;
use EvoSC\Models\MapQueue;
use EvoSC\Models\Player;
use EvoSC\Modules\Dedimania\Dedimania;
use EvoSC\Modules\LocalRecords\LocalRecords;
use EvoSC\Modules\MapList\Models\MapFavorite;
use EvoSC\Modules\MxDetails\MxDetails;
use EvoSC\Modules\QuickButtons\QuickButtons;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Class MapController
 *
 * @package EvoSC\Controllers
 */
class MapController implements ControllerInterface
{
    private static Map $currentMap;
    private static ?Map $nextMap;
    private static string $mapsPath;
    private static Collection $mapToDisable;

    /**
     * Initialize MapController
     */
    public static function init()
    {
        global $_skipMapCheck;

        if (config('server.map-cooldown') == 0) {
            Log::error('Map cooldown must be 1 or greater.', true);
            exit(3); //Configuration error
        }

        if (!File::dirExists(cacheDir('gbx'))) {
            File::makeDir(cacheDir('gbx'));
        }

        self::$mapsPath = Server::getMapsDirectory();
        self::$mapToDisable = collect();
        self::$currentMap = Map::getByUid(Server::getCurrentMapInfo()->uId);

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
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMatch', [self::class, 'processMapsToDisable']);
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
        }
    }

    /**
     * @param Map $map
     * @throws GuzzleException
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

        MxDetails::loadMxDetails($map);
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
            self::$nextMap = Map::getByUid($mapUid);
        }
    }

    /**
     * Get the currently played map.
     *
     * @return Map
     */
    public static function getCurrentMap(): ?Map
    {
        if (!isset(self::$currentMap)) {
            list($childClass, $caller) = debug_backtrace(false, 2);
            Log::warning('Current map is not set, called from: ' . implode('', [basename($caller['class']), $caller['type'], $caller['function']]), true);
            return null;
        }

        return self::$currentMap;
    }

    public static function current(): Map
    {
        return self::getCurrentMap();
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
            QueueController::dropMapSilent($map->uid);
        } else {
            Log::write('Failed to delete map "' . $map->filename);
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
        Log::write($player . '(' . $player->Login . ') queued disabling map ' . $map . ' [' . $map->uid . ']');

        QueueController::dropMapSilent($map->uid);
        self::$mapToDisable->push($map);
    }

    public static function processMapsToDisable()
    {
        foreach (self::$mapToDisable as $map) {
            QueueController::dropMapSilent($map->uid);
            $map->update(['enabled' => 0]);
            MatchSettingsController::removeByFilenameFromCurrentMatchSettings($map->filename);
            Log::info('Disabled map ' . $map->uid);
        }

        Hook::fire('MapPoolUpdated');
        self::$mapToDisable = collect();
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
     * @throws GuzzleException
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
     * @return string|MPS_Map
     */
    public static function getGbxInformation($filename, bool $asString = true)
    {
        $mps = Server::GameDataDirectory() . (isWindows() ? DIRECTORY_SEPARATOR : '') . '..' . DIRECTORY_SEPARATOR . 'ManiaPlanetServer';
        $mapFile = Server::GameDataDirectory() . 'Maps' . DIRECTORY_SEPARATOR . $filename;
        $cmd = $mps . sprintf(' /parsegbx="%s"', $mapFile);
        $jsonString = shell_exec($cmd);

        if ($asString) {
            return $jsonString;
        }

        $data = json_decode($jsonString);
        $data->fileName = $filename;

        return MPS_Map::fromObject($data);
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
                'enabled' => 1
            ]);

            echo '.';
        }

        echo "\n";
    }

    /**
     * @param string $login
     * @return int|mixed
     */
    public static function createOrGetAuthor(string $login)
    {
        $author = DB::table('players')->where('Login', '=', $login)->first();

        if ($author) {
            $authorId = $author->id;
        } else {
            $authorId = DB::table('players')->insertGetId([
                'Login' => $login,
                'NickName' => $login
            ]);
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
     * @return stdClass
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
     * @return Collection
     */
    public static function getMapToDisable(): Collection
    {
        return self::$mapToDisable;
    }
}