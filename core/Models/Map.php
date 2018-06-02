<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Map extends Model
{
    protected $table = 'maps';

    protected $fillable = ['uid', 'filename', 'plays', 'author', 'last_played', 'enabled', 'mx_details', 'mx_world_record', 'gbx'];

    protected $dates = ['last_played'];

    public $timestamps = false;

    public function locals()
    {
        return $this->hasMany(LocalRecord::class, 'Map');
    }

    public function dedis()
    {
        return $this->hasMany(Dedi::class, 'Map');
    }

    public function author()
    {
        return $this->hasOne(Player::class, 'id', 'author');
    }

    public function getAuthorAttribute($playerId)
    {
        return Player::whereId($playerId)->first();
    }

    public function ratings()
    {
        return $this->hasMany(Karma::class, 'Map', 'id');
    }

    public function favorites()
    {
        return $this->belongsToMany(Player::class, 'map-favorites');
    }

    public function getMxDetailsAttribute($jsonMxDetails)
    {
        if ($jsonMxDetails) {
            $data = json_decode($jsonMxDetails);

            if (array_key_exists(0, $data)) {
                return $data[0];
            }
        }

        return null;
    }

    public function getMxWorldRecordAttribute($jsonMxWorldRecordDetails)
    {
        return json_decode($jsonMxWorldRecordDetails);
    }

    public function getGbxAttribute($gbxJson)
    {
        return json_decode($gbxJson);
    }

    public function canBeJuked(): bool
    {
        $lastPlayedDate = $this->last_played;

        if ($lastPlayedDate) {
            return $this->last_played->diffInSeconds() > 1800;
        }

        return true;
    }

    public function __toString()
    {
        return stripAll($this->gbx->Name);
    }
}