<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccessRight extends Model
{
    protected $table = 'access-rights';

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }
}