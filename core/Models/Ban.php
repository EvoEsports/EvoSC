<?php

namespace EvoSC\Models;


use Illuminate\Database\Eloquent\Model;

class Ban extends Model
{
    protected $table = 'bans';

    public $timestamps = false;

    protected $dates = ['dob'];

    protected $fillable = ['player_id', 'banned_by', 'dob', 'length', 'reason'];

    public function player()
    {
        return $this->hasOne(Player::class, 'id');
    }

    public function bannedBy()
    {
        $this->hasOne(Player::class, 'id', 'banned_by');
    }
}