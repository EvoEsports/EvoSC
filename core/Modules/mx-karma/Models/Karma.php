<?php

namespace esc\Models;

class Karma extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mx-karma';

    protected $fillable = ['Player', 'Map', 'Rating'];

    public $timestamps = false;
}