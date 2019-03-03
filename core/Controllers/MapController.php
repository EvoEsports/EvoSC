<?php

namespace esc\Controllers;


use esc\Classes\Config;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\MapQueue;
use esc\Models\Player;
use esc\Modules\QuickButtons;
use Illuminate\Support\Carbon;
use Maniaplanet\DedicatedServer\Xmlrpc\FileException;
use mysql_xdevapi\Exception;

class MapController implements ControllerInterface
{
    private static $mapsPath;
    private static $currentMap;
    private static $addedTime = 0;
    private static $timeLimit;

    public static function init()
    {
        self::$mapsPath  = Server::getMapsDirectory();
        self::$timeLimit = self::getTimeLimitFromMatchSettings();

        self::loadMaps();

        Hook::add('BeginMap', [MapController::class, 'beginMap']);
        Hook::add('BeginMatch', [MapController::class, 'beginMatch']);
        Hook::add('EndMatch', [MapController::class, 'endMatch']);

        ChatController::addCommand('skip', [MapController::class, 'skip'], 'Skips map instantly', '//', 'map_skip');
        ChatController::addCommand('settings', [MapController::class, 'settings'], 'Load match settings', '//', 'matchsettings_load');
        ChatController::addCommand('res', [MapController::class, 'forceReplay'], 'Queue map for replay', '//', 'map_replay');
        ChatController::addCommand('addtime', [MapController::class, 'addTimeManually'], 'Adds time (you can also substract)', '//', 'time');

        ManiaLinkEvent::add('map.skip', [MapController::class, 'skip'], 'map.skip');
        ManiaLinkEvent::add('map.replay', [MapController::class, 'forceReplay'], 'map.replay');
        ManiaLinkEvent::add('map.reset', [MapController::class, 'resetRound'], 'map.reset');

        AccessRight::createIfNonExistent('map_skip', 'Skip map instantly.');
        AccessRight::createIfNonExistent('map_add', 'Add map permanently.');
        AccessRight::createIfNonExistent('map_delete', 'Delete map permanently.');
        AccessRight::createIfNonExistent('map_disable', 'Disable map.');
        AccessRight::createIfNonExistent('map_replay', 'Force a replay.');
        AccessRight::createIfNonExistent('map_reset', 'Reset round.');
        AccessRight::createIfNonExistent('matchsettings_load', 'Load matchsettings.');
        AccessRight::createIfNonExistent('matchsettings_edit', 'Edit matchsettings.');
        AccessRight::createIfNonExistent('time', 'Change the countdown time.');

        KeyController::createBind('Q', [self::class, 'addMinute'], 'time');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Skip Map', 'map.skip', 'map_skip');
            QuickButtons::addButton('', 'Replay Map', 'map.replay', 'map_replay');
            QuickButtons::addButton('', 'Reset Round', 'map.reset', 'map_reset');
        }
    }

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

    public static function addMinute(Player $player)
    {
        self::addTime(60);
    }

    public static function addTimeManually(Player $player, $cmd, float $amount)
    {
        self::addTime($amount * 60.0);
        Log::logAddLine('MapController', $player . ' added ' . $amount . ' minutes');
    }

    public static function setTimelimit(int $seconds)
    {
        $settings                = Server::getModeScriptSettings();
        $settings['S_TimeLimit'] = $seconds;
        Server::setModeScriptSettings($settings);
    }

    /**
     * Hook: EndMatch
     *
     * @param $rankings
     * @param $winnerteam
     */
    public static function endMatch()
    {
        $request = MapQueue::getFirst();

        if ($request) {
            Log::info("Setting next map: " . $request->map);
            Server::chooseNextMap($request->map->filename);
            MapQueue::removeFirst();
            Hook::fire('MapQueueUpdated', QueueController::getMapQueue());
            ChatController::message(onlinePlayers(), '_info', '$fff', ' Upcoming map ', secondary($request->map), ' requested by ', $request->player);
        } else {
            $nextMap = Map::where('uid', Server::getNextMapInfo()->uId)->first();
            ChatController::message(onlinePlayers(), '_info', '$fff', ' Upcoming map ', secondary($nextMap));
        }
    }

    /*
     * Hook: BeginMap
     */
    public static function beginMap(Map $map)
    {
        Map::where('id', '!=', $map->id)->increment('cooldown');

        $map->increment('plays');
        $map->update([
            'last_played' => Carbon::now(),
            'cooldown'    => 0,
        ]);

        self::loadMxDetails($map);

        foreach (finishPlayers() as $player) {
            $player->setScore(0);
        }

        self::$currentMap = $map;
    }

    public static function beginMatch()
    {
        self::resetTime();
        self::addTime(1);
    }

    /**
     * Gets current map
     *
     * @return Map|null
     */
    public static function getCurrentMap(): ?Map
    {
        if (!self::$currentMap) {
            throw new Exception("Current map is null");
        }

        return self::$currentMap;
    }

    public static function deleteMap(Player $player, Map $map)
    {
        try {
            Server::removeMap($map->filename);
        } catch (FileException $e) {
            Log::error($e);
        }

        $deleted = File::delete(Config::get('server.maps') . '/' . $map->filename);

        if ($deleted) {
            ChatController::message(onlinePlayers(), '_info', $player, ' removed map ', $map);
            try {
                $map->delete();
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
                ChatController::message(onlinePlayers(), '_info', $player, ' deleted map ', secondary($map), 'permanently.');
                Hook::fire('MapPoolUpdated');
            } catch (\Exception $e) {
                Log::logAddLine('MapController', 'Failed to deleted map: ' . $e->getMessage());
            }
        }
    }

    public static function disableMap(Player $player, Map $map)
    {
        try {
            Server::removeMap($map->filename);
        } catch (FileException $e) {
            Log::error($e);
        }

        $map->update(['enabled' => false]);
        Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));

        ChatController::message(onlinePlayers(), '_info', $player->group, ' ', $player, ' disabled map ', secondary($map));
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
            ChatController::message(onlinePlayers(), '_info', $player, ' skips map');
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

    public static function getGbxInformation($filename): string
    {
        $absolute = Server::getMapsDirectory() . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $filename);
        $cmd      = Server::GameDataDirectory() . '/../ManiaPlanetServer /parsegbx="' . $absolute . '"';

        return shell_exec($cmd);
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

            $map = Map::where('uid', $mapInfo->uId)
                      ->get()
                      ->first();

            if (!$map) {
                //Map does not exist, create it
                $author = Player::where('Login', $mapInfo->author)->first();

                if ($author) {
                    $authorId = $author->id;
                } else {
                    $authorId = Player::insertGetId([
                        'Login'    => $mapInfo->author,
                        'NickName' => $mapInfo->author,
                    ]);
                }

                $gbxInfo = self::getGbxInformation($mapInfo->fileName);

                $map = Map::updateOrCreate([
                    'author'   => $authorId,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'filename' => $mapInfo->fileName,
                    'uid'      => json_decode($gbxInfo)->MapUid,
                ]);
            }

            if (!$map->gbx) {
                $gbxInfo = self::getGbxInformation($mapInfo->fileName);
                $map->update([
                    'gbx' => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'uid' => json_decode($gbxInfo)->MapUid,
                ]);
            }

            echo ".";
        }

        echo "\n";

        //Disable maps
        Map::whereNotIn('uid', $enabledMapsuids)
           ->update(['enabled' => false]);

        //Enable loaded maps
        Map::whereIn('uid', $enabledMapsuids)
           ->update(['enabled' => true]);
    }

    public static function loadMxDetails(Map $map, bool $overwrite = false)
    {
        if ($map->mx_details != null && !$overwrite) {
            return;
        }

        $result = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $map->uid);

        if ($result->getStatusCode() != 200) {
            Log::logAddLine('MapController', 'Failed to fetch MX details: ' . $result->getReasonPhrase());

            return;
        }

        $data = $result->getBody()->getContents();

        if ($data == '[]') {
            Log::logAddLine('MapController', 'No MX information available for: ' . $map->gbx->Name);

            return;
        }

        $map->update(['mx_details' => $data]);
        Log::logAddLine('MapController', 'Updated MX details for track: ' . $map->gbx->Name);

        $mxDetails = json_decode($data);

        if (count($mxDetails) == 0) {
            Log::logAddLine('MapController', 'Failed to fetch MX world record: mxDetails is empty.');

            return;
        }

        $mxDetails = $mxDetails[0];

        $result = RestClient::get('https://api.mania-exchange.com/tm/tracks/worldrecord/' . $mxDetails->TrackID);

        if ($result->getStatusCode() != 200) {
            Log::logAddLine('MapController', 'Failed to fetch MX world record: ' . $result->getReasonPhrase());

            return;
        }
        $map->update(['mx_world_record' => $result->getBody()->getContents()]);

        Log::logAddLine('MapController', 'Updated MX world record for track: ' . $map->gbx->Name);
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
    public static function getAddedTime(): int
    {
        return self::$addedTime;
    }

    public static function resetRound(Player $player)
    {
        Server::restartMap();
    }

    public static function getMapsPath(): string
    {
        return self::$mapsPath;
    }
}