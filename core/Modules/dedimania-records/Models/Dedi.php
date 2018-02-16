<?php

use esc\models\Player;

class Dedi extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'dedi-records';

    protected $fillable = ['Map', 'Player', 'Score', 'Rank'];

    public $timestamps = false;

    public function map()
    {
        return $this->hasOne('esc\models\Map', 'id', 'Map');
    }

    public function player()
    {
        return $this->hasOne('esc\models\Player', 'id', 'Player');
    }
}