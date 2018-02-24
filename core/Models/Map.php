<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $table = 'maps';

    protected $fillable = ['UId', 'MxId', 'Name', 'FileName', 'Plays', 'Author', 'Mood', 'LapRace', 'LastPlayed'];

    public $timestamps = false;

    public function locals()
    {
        return $this->hasMany('LocalRecord', 'Map', 'id');
    }

    public function dedis(){
        return $this->hasMany('Dedi', 'Map');
    }

    public function author(){
        return $this->hasOne('esc\Models\Player', 'Login', 'Author');
    }
}