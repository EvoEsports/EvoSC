<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $table = 'groups';

    protected $fillable = ['Name'];

    public $timestamps = false;

    public function hasAccess(string $accessRightName)
    {
        if ($this->id == 1) {
            //Masteradrmin always has access
            return true;
        }

        return $this->accessRights->where('name', $accessRightName)->isNotEmpty();
    }

    public function accessRights()
    {
        return $this->belongsToMany(AccessRight::class);
    }

    public function player()
    {
        return $this->hasMany(Player::class, 'Group');
    }

    public function __toString()
    {
        return $this->Name;
    }
}