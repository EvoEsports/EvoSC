<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $table = 'groups';

    public function accessRights()
    {
        return $this->belongsToMany(AccessRight::class);
    }
}