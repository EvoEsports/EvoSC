<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Map;
use EvoSC\Models\MapQueue;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;

/**
 * Class QueueController
 *
 * The QueueController handles adding/removing maps to/from the queue.
 *
 * @package EvoSC\Controllers
 */
class QueueController implements ControllerInterface
{
    private static bool $preCache = false;

    /**
     * @inheritDoc
     */
    public static function init()
    {
        AccessRight::add('map_queue_recent', 'Juke recently played maps.');
        AccessRight::add('map_queue_drop', 'Drop maps from queue.');
        AccessRight::add('map_queue_multiple', 'Queue more than one map.');
        AccessRight::add('map_queue_keep', 'Keep maps in queue if player leaves.');
    }

    /**
     * @inheritDoc
     *
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        self::$preCache = (bool)config('server.pre-cache-maps', false);

        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMatch', [self::class, 'endMatch']);

        ManiaLinkEvent::add('map.queue', [self::class, 'manialinkQueueMap']);
        ManiaLinkEvent::add('map.drop', [self::class, 'dropMap']);

        ChatCommand::add('/drop', [self::class, 'cmdDropMapFromQueue'], 'Drops your upmost map on the queue.');
        ChatCommand::add('//dropall', [self::class, 'cmdDropAllMapsFromQueue'], 'Drops all maps from queue.', 'map_queue_drop');
    }

    /**
     * Add a map to the queue by its uid.
     *
     * @param Player $player
     * @param string $mapUid
     * @param int $cooldown
     * @return bool
     */
    public static function queueMapByUid(Player $player, string $mapUid, int $cooldown = 999): bool
    {
        if ($cooldown < config('server.map-cooldown') && !$player->hasAccess('map_queue_recent')) {
            warningMessage('Can not queue recently played track. Please wait ' . secondary(config('server.map-cooldown') - $cooldown) . ' maps.')->send($player);

            return false;
        }

        if (DB::table(Map::TABLE)->where('uid', '=', $mapUid)->where('enabled', '=', 0)->exists() || MapController::getMapToDisable()->contains('uid', $mapUid)) {
            warningMessage('Can not queue disabled map.')->send($player);

            return false;
        }

        if (DB::table(MapQueue::TABLE)->where('map_uid', '=', $mapUid)->count()) {
            warningMessage('The map is already in the queue.')->send($player);

            return false;
        }

        if (DB::table(MapQueue::TABLE)->where('requesting_player', '=', $player->Login)->count()) {
            if (!$player->hasAccess('map_queue_multiple')) {
                warningMessage('You are only allowed to queue one map at a time.')->send($player);

                return false;
            }
        }

        $mapQueueItem = MapQueue::create([
            'requesting_player' => $player->Login,
            'map_uid' => $mapUid,
        ]);

        Log::write($player . '(' . $player->Login . ') queued map ' . $mapQueueItem->map->name . ' [' . $mapUid . ']');
        Hook::fire('MapQueueUpdated', self::getMapQueue());

        if (self::$preCache) {
            self::chooseNextMap();
        }

        return true;
    }

    /**
     * Put a map object in queue.
     *
     * @param Player $player
     * @param Map $map
     */
    public static function queueMap(Player $player, Map $map)
    {
        if (self::queueMapByUid($player, $map->uid, $map->cooldown)) {
            infoMessage(secondary($player), ' queued map ', secondary($map->name))->sendAll();
        }
    }

    /**
     * @param Player $player
     * @param $cmd
     * @throws \Exception
     */
    public static function cmdDropMapFromQueue(Player $player, $cmd)
    {
        /**
         * @var MapQueue $mapQueueItem
         */
        $mapQueueItem = MapQueue::where('requesting_player', '=', $player->Login)
            ->orderByDesc('created_at')
            ->first();

        if ($mapQueueItem) {
            infoMessage($player, ' dropped map ', secondary($mapQueueItem->map), ' from the queue.')->sendAll();
            $mapQueueItem->delete();
            Hook::fire('MapQueueUpdated', self::getMapQueue());
        }
    }

    /**
     * @param Player $player
     * @param $cmd
     */
    public static function cmdDropAllMapsFromQueue(Player $player, $cmd)
    {
        MapQueue::truncate();
        warningMessage($player, ' cleared the jukebox.')->sendAll();
        Hook::fire('MapQueueUpdated', self::getMapQueue());
    }

    /**
     * @param Map $map
     */
    public static function beginMap(Map $map)
    {
        self::$preCache = true;

        self::dropMapSilent($map->uid);
    }

    public static function endMatch()
    {
        self::$preCache = false;
    }

    /**
     * Drop a map from queue by its uid.
     *
     * @param Player $player
     * @param                    $mapUid
     */
    public static function dropMap(Player $player, $mapUid)
    {
        $queueItem = MapQueue::whereMapUid($mapUid)->first();

        if ($queueItem) {
            if ($queueItem->requesting_player != $player->Login && !$player->hasAccess('map_queue_drop')) {
                warningMessage('You can not drop others players maps.')->send($player);

                return;
            }

            infoMessage($player, ' drops ', secondary($queueItem->map), ' from queue.')->sendAll();
            self::dropMapSilent($mapUid);

            if (self::$preCache) {
                self::chooseNextMap();
            }
        }
    }

    /**
     * Drop a map without info-message.
     *
     * @param $mapUid
     */
    public static function dropMapSilent($mapUid)
    {
        if (DB::table(MapQueue::TABLE)->where('map_uid', '=', $mapUid)->exists()) {
            DB::table(MapQueue::TABLE)->where('map_uid', '=', $mapUid)->delete();
            Hook::fire('MapQueueUpdated', self::getMapQueue());
        }
    }

    /**
     * @param Player $player
     * @param $mapUid
     */
    public static function manialinkQueueMap(Player $player, $mapUid)
    {
        $map = Map::getByUid($mapUid);

        if ($map) {
            QueueController::queueMap($player, $map);
        }
    }

    /**
     * Get maps in queue sorted by adding time.
     *
     * @return Collection
     */
    public static function getMapQueue(): Collection
    {
        return MapQueue::orderBy('created_at')->get();
    }

    /**
     * @param Player $player
     */
    public static function playerDisconnect(Player $player)
    {
        if (MapQueue::where('requesting_player', $player->Login)->exists()) {
            if ($player->hasAccess('map_queue_keep')) {
                //Keep maps of players with queue_keep right

                return;
            }

            $queueItems = MapQueue::where('requesting_player', $player->Login)->get();

            $queueItems->each(function (MapQueue $queueItem) use ($player) {
                MapQueue::whereMapUid($queueItem->map_uid)->delete();
                infoMessage('Dropped ', secondary($queueItem->map), ' from queue, because ', secondary($player),
                    ' left.')->sendAll();
                Log::write('Dropped map ' . $queueItem->map . ' from queue, because ' . $player . ' left.');
            });

            Hook::fire('MapQueueUpdated', self::getMapQueue());
        }
    }

    /**
     * Tell the QueueController to pick the next map.
     */
    public static function chooseNextMap()
    {
        $map = null;

        if (DB::table(MapQueue::TABLE)->count()) {
            $firstQueueItem = MapQueue::orderBy('created_at')->first();

            if (!$firstQueueItem) {
                $map = Map::whereEnabled(1)->inRandomOrder()->first();
            } else {
                $map = $firstQueueItem->map;
            }

        } else {
            $map = Map::whereEnabled(1)->inRandomOrder()->first();
        }

        /** @var \Maniaplanet\DedicatedServer\Structures\Map $nextMap */
        $nextMap = Server::getNextMapInfo();

        if ($map && $nextMap) {
            if ($nextMap->uId != $map->uid && Server::isFilenameInSelection($map->filename)) {
                Log::write('QueueController', sprintf('Pre-caching map %s [%s]', $map->name, $map->uid));

                try {
                    Server::chooseNextMap($map->filename);
                } catch (\Exception $e) {
                    Log::errorWithCause('Failed to pre-cache map ' . $map->name, $e);

                    self::dropMapSilent($map->uid);
                }
            }
        }
    }

    public static function dropAllMaps()
    {
        MapQueue::truncate();
        Hook::fire('MapQueueUpdated', collect([]));
    }
}
