<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

class InfoMessage extends Model
{
    protected $table = 'info-messages';

    public $timestamps = false;

    protected $fillable = ['text', 'delay'];
}