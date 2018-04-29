<?php

namespace esc\Models;

use Illuminate\Database\Eloquent\Model;

class DedimaniaSession extends Model
{
    protected $table = 'dedi-sessions';

    protected $fillable = ['Expired', 'Session'];
}