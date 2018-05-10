<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Map extends Model
{
    protected $table = 'maps';

    protected $fillable = ['UId', 'MxId', 'Name', 'FileName', 'Plays', 'Author', 'Mood', 'LapRace', 'LastPlayed', 'Environment', 'NbLaps', 'NbCheckpoints', 'AuthorTime', 'Enabled', 'mx_details', 'mx_world_record'];

    protected $dates = ['LastPlayed'];

    public $timestamps = false;

    public function locals()
    {
        return $this->hasMany(LocalRecord::class, 'Map', 'id');
    }

    public function dedis()
    {
        return $this->hasMany(Dedi::class, 'Map');
    }

    public function author()
    {
        return $this->hasOne(Player::class, 'Login', 'Author');
    }

    public function ratings()
    {
        return $this->hasMany(Karma::class, 'Map', 'id');
    }

    public function getMxDetailsAttribute($jsonMxDetails)
    {
        return json_decode($jsonMxDetails)[0];
    }

    public function canBeJuked(): bool
    {
        $lastPlayedDate = $this->LastPlayed;

        if ($lastPlayedDate) {
            return $this->LastPlayed->diffInSeconds() > 1800;
        }

        return true;
    }
}