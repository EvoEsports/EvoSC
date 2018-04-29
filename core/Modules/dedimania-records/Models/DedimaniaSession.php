<?php

namespace esc\Models;

class DedimaniaSession extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'dedi-sessions';

    protected $fillable = ['Expired', 'Session'];
}