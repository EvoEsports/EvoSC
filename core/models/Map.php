<?php

namespace esc\models;


use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $table = 'maps';

    protected $primaryKey = 'UId';

    public $timestamps = false;
}