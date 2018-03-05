<?php

use esc\Models\Map;
use esc\models\Player;

class Dedi extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'dedi-records';

    protected $fillable = ['Map', 'Player', 'Score', 'Rank'];

    public $timestamps = false;

    public function player()
    {
        return $this->hasOne(Player::class, 'id', 'Player');
    }

    public function map()
    {
        return $this->hasOne(Map::class, 'id', 'Map');
    }
}