<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\MapQueue;
use esc\Models\Player;
use Illuminate\Support\Collection;

class QueueController
{
    public static function init()
    {
        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect']);

        ManiaLinkEvent::add('map.queue', [self::class, 'manialinkQueueMap']);
        ManiaLinkEvent::add('map.drop', [self::class, 'dropMap']);

        AccessRight::createIfNonExistent('map_queue_recent', 'Drop maps from queue.');
        AccessRight::createIfNonExistent('queue_drop', 'Drop maps from queue.');
        AccessRight::createIfNonExistent('queue_keep', 'Keep maps in queue if player leaves.');
    }

    public static function queueMap(Player $player, Map $map, bool $replay = false)
    {
        if (MapQueue::whereMapUid($map->uid)->count() > 0) {
            ChatController::message($player, '_warning', 'The map ', secondary($map), ' is already in queue.');

            return;
        }

        if (MapQueue::whereRequestingPlayer($player->Login)->count() > 0) {
            if (!$player->hasAccess('queue_multiple')) {
                ChatController::message($player, '_warning', 'You are only allowed to queue one map at a time.');

                return;
            }
        }

        MapQueue::create([
            'requesting_player' => $player->Login,
            'map_uid'           => $map->uid,
        ]);

        if($replay){
            ChatController::message(onlinePlayers(), '_info', $player, ' queued map ', secondary($map), ' for replay.');
        }else{
            ChatController::message(onlinePlayers(), '_info', $player, ' queued map ', secondary($map), '.');
        }

        Hook::fire('MapQueueUpdated', self::getMapQueue());
    }

    public static function dropMap(Player $player, $mapUid)
    {
        $queueItem = MapQueue::whereMapUid($mapUid)->first();

        if ($queueItem) {
            if ($queueItem->requesting_player == $player->Login || $player->hasAccess('queue_drop')) {
                ChatController::message(onlinePlayers(), '_info', $player, ' drops ', secondary($queueItem->map), ' from queue.');
                MapQueue::whereMapUid($mapUid)->delete();
                Hook::fire('MapQueueUpdated', self::getMapQueue());

                return;
            }

            ChatController::message(onlinePlayers(), '_warning', $player, 'You can not drop others players maps.');
        }
    }

    public static function manialinkQueueMap(Player $player, $mapUid)
    {
        $map = Map::whereUid($mapUid)->first();

        if ($map) {
            QueueController::queueMap($player, $map);
        }
    }

    public static function getMapQueue(): Collection
    {
        return MapQueue::orderBy('created_at')->get();
    }

    public static function playerDisconnect(Player $player)
    {
        $queryBuilder = MapQueue::whereRequestingPlayer($player->Login);

        if (!$player->hasAccess('queue_keep') && $queryBuilder->count() > 0) {
            $queryBuilder->get()->filter(function (MapQueue $item) use ($player) {
                ChatController::message(onlinePlayers(), '_info', 'Dropped ', secondary($item->map), ' from queue, because ', secondary($player), ' left.');
                MapQueue::whereMapUid($item->map_uid)->delete();
            });

            Hook::fire('MapQueueUpdated', self::getMapQueue());
        }
    }
}