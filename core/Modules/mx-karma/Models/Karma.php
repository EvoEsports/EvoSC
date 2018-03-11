<?php

use esc\Models\Player;

class Karma extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mx-karma';

    protected $fillable = ['Player', 'Map', 'rating'];

    public $timestamps = false;

    public function player()
    {
        return $this->belongsTo(Player::class, 'Player', 'id');
    }
}