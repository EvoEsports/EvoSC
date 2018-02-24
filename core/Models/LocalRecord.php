<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

class LocalRecord extends Model
{
    protected $table = 'local-records';

    public $timestamps = false;

    public function player(){
        return $this->hasOne('esc\Models\Player', 'Login', 'player');
    }

    public function map(){
        return $this->hasOne('esc\Models\Map', 'UId', 'map');
    }
}