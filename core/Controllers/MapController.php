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
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Map;
use EvoSC\Models\MapQueue;
use EvoSC\Models\Player;
use EvoSC\Modules\Dedimania\Dedimania;
use EvoSC\Modules\LocalRecords\LocalRecords;
use EvoSC\Modules\MapList\Models\MapFavorite;
use EvoSC\Modules\MxDownload\MxDownload;
use EvoSC\Modules\QuickButtons\QuickButtons;
use EvoSC\Modules\Statistics\Statistics;
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
    private static int $round = -1;
    private static int $playersFinished = 0;

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
            File::makeDir(cacheDir('gbx'));  // Throws an error in Windows system with the original regex settings
        }

        if (!$_skipMapCheck) {
            self::loadMaps();
        }

        self::$mapsPath = Server::getMapsDirectory();
        self::$mapToDisable = collect();
        self::$currentMap = Map::getByUid(Server::getCurrentMapInfo()->uId);

        AccessRight::add('map_skip', 'Skip map instantly.');
        AccessRight::add('map_add', 'Add map permanently.');
        AccessRight::add('map_delete', 'Delete map (and all records) permanently.');
        AccessRight::add('map_disable', 'Disable map.');
        AccessRight::add('map_replay', 'Force a replay.');
        AccessRight::add('map_reset', 'Reset round.');
        AccessRight::add('force_end_round', 'Force the end of a round (Rounds/Laps).');
        AccessRight::add('manipulate_time', 'Change the countdown time.');
        AccessRight::add('manipulate_points', 'Change the points-limit.');
        AccessRight::add('matchsettings_load', 'Load matchsettings.');
        AccessRight::add('matchsettings_edit', 'Edit matchsettings.');
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
        Hook::add('Maniaplanet.Podium_Start', [self::class, 'endMatch']);

        ChatCommand::add('//skip', [self::class, 'skip'], 'Skips map instantly', 'map_skip');
        ChatCommand::add('//settings', [self::class, 'settings'], 'Load match settings', 'matchsettings_load');
        ChatCommand::add('//res', [self::class, 'forceReplay'], 'Queue map for replay', 'map_replay');
        ChatCommand::add('//endround', [self::class, 'cmdEndRound'], 'Force the round to end (rounds, cup, ...)', 'force_end_round');
        ChatCommand::add('/next', [self::class, 'cmdNextMap'], 'Print the upcoming map to chat.');

        ManiaLinkEvent::add('map.skip', [self::class, 'skip'], 'map_skip');
        ManiaLinkEvent::add('map.replay', [self::class, 'forceReplay'], 'map_replay');
        ManiaLinkEvent::add('map.reset', [self::class, 'resetRound'], 'map_reset');
        ManiaLinkEvent::add('force_end_round', [self::class, 'mleForceEndOfRound'], 'force_end_round');

        QuickButtons::addButton('', 'Skip Map', 'map.skip', 'map_skip');
        QuickButtons::addButton('', 'Reset Match', 'map.reset', 'map_reset');

        if (ModeController::isRoundsType()) {
            self::$round = 1;
            Hook::add('PlayerFinish', [self::class, 'playerFinish']);
            Hook::add('Maniaplanet.StartPlayLoop', [self::class, 'startPlayLoop']);
            Hook::add('Trackmania.WarmUp.End', [self::class, 'resetRoundCounter']);
            Hook::add('BeginMap', [self::class, 'resetRoundCounter']);
            QuickButtons::addButton('', 'Force end of round', 'force_end_round', 'force_end_round');
            self::resetRoundCounter();
        }
    }

    /**
     * @param Player $player
     * @param int $score
     */
    public static function playerFinish(Player $player, int $score)
    {
        if ($score > 0) {
            self::$playersFinished++;
        }
    }

    /**
     *
     */
    public static function startPlayLoop()
    {
        if (ModeController::cup() && self::$playersFinished == 0) {
            return;
        }

        self::$round++;
        self::sendUpdatedRound();
        self::$playersFinished = 0;
    }

    /**
     *
     */
    public static function resetRoundCounter()
    {
        self::$round = 0;
        self::sendUpdatedRound();
    }

    /**
     *
     */
    public static function sendUpdatedRound()
    {
        Template::showAll('Helpers.update-round', ['round' => self::$round]);
    }

    /**
     * @param Player $player
     * @param $cmd
     */
    public static function cmdEndRound(Player $player, $cmd)
    {
        self::mleForceEndOfRound($player);
    }

    /**
     * @param Player $player
     */
    public static function mleForceEndOfRound(Player $player)
    {
        Server::triggerModeScriptEventArray('Trackmania.ForceEndRound');
        Server::triggerModeScriptEventArray('Trackmania.WarmUp.ForceStopRound');
        warningMessage(secondary($player), ' forced the round to end.')->sendAll();
    }

    /**
     * @param Player $player
     * @param $cmd
     */
    public static function cmdNextMap(Player $player, $cmd)
    {
        $queue = QueueController::getMapQueue();

        if ($queue->isNotEmpty()) {
            $queueItem = $queue->first();
            infoMessage('The next map is ', secondary($queueItem->map->name), ' requested by ', secondary($queueItem->player->NickName))->send($player);
        } else {
            $nextMap = Server::getNextMapInfo()->name;
            infoMessage('The next map is ', secondary($nextMap))->send($player);
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

        if (!$map->mx_details) {
            $mx_details = MxDownload::loadMxDetails($map->uid);

            if (!is_null($mx_details)) {
                if ($map->author->Login == $map->author->NickName) {
                    $map->author->update(['NickName' => $mx_details->Username]);
                }

                if (is_null($map->exchange_version)) {
                    $map->update(['exchange_version' => $mx_details->UpdatedAt]);
                    $map->exchange_version = $mx_details->UpdatedAt;
                }

                if (strtotime($mx_details->UpdatedAt) > strtotime($map->exchange_version)) {
                    dangerMessage('There is an update available for this map! Call ', secondary('//add ' . $map->mx_id), ' to update.')->sendAdmin();
                }
            }
        }
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
                    Log::errorWithCause('Adding map to selection failed', $e);
                }
            }

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
                $message = 'Failed to delete map ' . $map->filename;
                Log::errorWithCause($message, $e);
            }
        }

        DB::table(LocalRecords::TABLE)->where('Map', '=', $map->id)->delete();
        DB::table(Dedimania::TABLE)->where('Map', '=', $map->id)->delete();
        MapFavorite::whereMapId($map->id)->delete();
        QueueController::dropMapSilent($map->uid);
        Hook::fire('MapPoolUpdated');
        $deleted = File::delete(self::$mapsPath . $map->filename);

        if ($deleted) {
            try {
                $map->delete();
                Log::write($player . '(' . $player->Login . ') deleted map ' . $map . ' [' . $map->uid . ']');
            } catch (Exception $e) {
                Log::errorWithCause('Failed to remove map "' . $map->uid . '" from database: ', $e);
            }

            MatchSettingsController::removeByFilenameFromCurrentMatchSettings($map->filename);

            warningMessage($player, ' deleted map ', $map)->sendAll();
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
                Log::errorWithCause('Failed to disable map', $e);
            }
        }

        infoMessage($player, ' disabled map ', secondary($map))->sendAll();
        Log::write($player . '(' . $player->Login . ') queued disabling map ' . $map . ' [' . $map->uid . ']');

        QueueController::dropMapSilent($map->uid);
        self::$mapToDisable->push($map);
    }

    /**
     * @param Player $player
     * @param Map $map
     */
    public static function enableMap(Player $player, Map $map)
    {
        infoMessage($player, ' enabled map ', secondary($map))->sendAll();
        $map->update(['enabled' => 1]);

        if (!Server::isFilenameInSelection($map->filename)) {
            try {
                Server::addMap($map->filename);
            } catch (Exception $e) {
                Log::errorWithCause('Failed to enable map', $e);
            }
        }
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
    public static function getGbxInformation($filename, bool $asString = false): ?MPS_Map
    {
        $mapFile = Server::GameDataDirectory() . 'Maps' . DIRECTORY_SEPARATOR . $filename;

        if (File::exists($mapFile)) {
            $mapsMap = MPS_Map::fromFilename($filename);
        } else {
            $mapsMap = MPS_Map::fromObject(Server::getMapInfo($filename));
        }

        if ($asString) {
            return json_encode($mapsMap);
        }

        return $mapsMap;
    }

    /**
     * Loads maps from server directory
     */
    public static function loadMaps()
    {
        Log::write('Loading maps');

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
                'folder' => substr($map->fileName, 0, strrpos($map->fileName, DIRECTORY_SEPARATOR)),
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
     * @param string|null $name
     * @return int|mixed
     */
    public static function createOrGetAuthor(string $login, string $name = null)
    {
        $author = DB::table('players')->where('Login', '=', $login)->first();

        if ($author) {
            $authorId = $author->id;
        } else {
            $authorId = DB::table('players')->insertGetId([
                'Login' => $login,
                'NickName' => $name ?: $login
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
        Statistics::beginMap();
        self::resetRoundCounter();
    }

    /**
     * @return Collection
     */
    public static function getMapToDisable(): Collection
    {
        return self::$mapToDisable;
    }
}
