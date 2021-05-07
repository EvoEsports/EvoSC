<?php

namespace EvoSC\Models;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Controllers\QueueController;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MapQueue
 * @package EvoSC\Models
 */
class MapQueue extends Model
{
    const TABLE = 'map-queue';
    protected $table = self::TABLE;

    protected $fillable = [
        'requesting_player',
        'map_uid',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function player()
    {
        return $this->hasOne(Player::class, 'Login', 'requesting_player');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function map()
    {
        return $this->hasOne(Map::class, 'uid', 'map_uid');
    }

    /**
     * @return MapQueue|null
     */
    public static function getFirst(): ?MapQueue
    {
        /**
         * @var MapQueue $mapQueue
         */
        $mapQueue = self::orderBy('created_at')->first();

        if($mapQueue){
            if (is_null($mapQueue->map) || $mapQueue->map->enabled == 0) {
                try {
                    $mapQueue->delete();

                    if (self::count() > 0) {
                        return self::getFirst();
                    }
                } catch (\Exception $e) {
                    Log::errorWithCause('Failed to retrieve map from queue', $e);
                }
            }
        }

        return $mapQueue;
    }

    /**
     *
     */
    public static function removeFirst()
    {
        self::orderBy('created_at')->first()->delete();
        Hook::fire('MapQueueUpdated', QueueController::getMapQueue());
    }
}
