<?php

namespace esc\models;


use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $table = 'maps';

    protected $fillable = ['MxId', 'Name', 'FileName'];

    public $timestamps = false;

    public function locals(){
        return $this->hasMany('LocalRecord', 'Map', 'id');
    }
}