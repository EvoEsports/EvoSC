<?php

namespace esc\Models;


use esc\Classes\Hook;
use esc\Controllers\QueueController;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MapQueue
 * @package esc\Models
 */
class MapQueue extends Model
{
    protected $table = 'map-queue';

    protected $fillable = [
        'requesting_player',
        'map_uid',
    ];

    public function player()
    {
        return $this->hasOne(Player::class, 'Login', 'requesting_player');
    }

    public function map()
    {
        return $this->hasOne(Map::class, 'uid', 'map_uid');
    }

    public static function getFirst(): ?MapQueue
    {
        return self::orderBy('created_at')->first();
    }

    public static function removeFirst()
    {
        self::orderBy('created_at')->first()->delete();
        Hook::fire('MapQueueUpdated', QueueController::getMapQueue());
    }
}