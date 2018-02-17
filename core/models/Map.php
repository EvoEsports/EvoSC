<?php

namespace esc\models;


use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $table = 'maps';

    protected $fillable = ['UId', 'MxId', 'Name', 'FileName', 'Plays', 'Author', 'Mood', 'LapRace'];

    public $timestamps = false;

    public function locals()
    {
        return $this->hasMany('LocalRecord', 'Map', 'id');
    }

    public function dedis(){
        return $this->hasMany('Dedi', 'Map');
    }

    public function author(){
        return $this->hasOne('esc\models\Player', 'Login', 'Author');
    }
}