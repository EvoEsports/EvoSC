<?php

namespace esc\Models;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $table = 'songs';

    protected $fillable = ['title', 'artist', 'album', 'year', 'length', 'url'];
}