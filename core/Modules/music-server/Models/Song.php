<?php

class Song extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'songs';

    protected $primaryKey = 'hash';

    protected $fillable = ['title', 'artist', 'album', 'year', 'length', 'url', 'hash'];
}