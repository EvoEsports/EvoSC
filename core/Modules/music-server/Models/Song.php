<?php

namespace esc\Models;

class Song extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'songs';

    protected $fillable = ['title', 'artist', 'album', 'year', 'length', 'url'];
}