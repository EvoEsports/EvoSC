<?php

use esc\Models\Player;

class Karma extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mx-karma';

    protected $fillable = ['Player', 'Map', 'Rating'];

    public $timestamps = false;
}