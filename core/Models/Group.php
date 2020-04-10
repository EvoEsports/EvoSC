<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Group
 * @package esc\Models
 * @property int $id;
 * @property string $Name;
 * @property string $chat_prefix;
 * @property string $color;
 */
class Group extends Model
{
    const MASTERADMIN = 1;
    const ADMIN = 2;
    const PLAYER = 3;

    protected $table = 'groups';

    protected $fillable = ['Name', 'chat_prefix', 'color'];

    public $timestamps = false;

    public function hasAccess(string $accessRightName)
    {
        if ($this->id == 1) {
            //Master admin always has access
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
        if ($this->color) {
            return '$' . $this->color . $this->Name;
        }

        return $this->Name;
    }
}