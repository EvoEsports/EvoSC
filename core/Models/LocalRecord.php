<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

class LocalRecord extends Model
{
    protected $table = 'local-records';

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