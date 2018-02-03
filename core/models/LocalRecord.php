<?php

namespace esc\models;


use Illuminate\Database\Eloquent\Model;

class LocalRecord extends Model
{
    protected $table = 'local-records';

    public $timestamps = false;

    public function player(){
        return $this->hasOne('esc\models\Player', 'Login', 'player');
    }

    public function map(){
        return $this->hasOne('esc\models\Map', 'UId', 'map');
    }
}