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
        return $this->accessRights->where('name', $accessRightName)->isNotEmpty();
    }

    public function accessRights()
    {
        return $this->belongsToMany(AccessRight::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class, 'id', 'Group');
    }
}