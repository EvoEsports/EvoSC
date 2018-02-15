<?php

namespace esc\models;


use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $table = 'maps';

    protected $fillable = ['MxId', 'Name', 'FileName', 'Plays', 'Author', 'Mood', 'LapRace'];

    public $timestamps = false;

    public function locals()
    {
        return $this->hasMany('LocalRecord', 'Map', 'id');
    }

    public function author(): ?Player
    {
        $player = Player::find($this->Author);

        if($player){
            return $player;
        }

        return $this->Author;
    }
}