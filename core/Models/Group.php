<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    public function hasAccess(string $accessRightName)
    {
        return $this->accessRights->where('name', $accessRightName)->isNotEmpty();
    }

    public function accessRights()
    {
        return $this->belongsToMany(AccessRight::class);
    }
}