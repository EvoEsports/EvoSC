<?php

namespace EvoSC\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Group
 * @package EvoSC\Models
 * @property int $id;
 * @property string $Name;
 * @property string $chat_prefix;
 * @property string $color;
 * @property bool $unrestricted;
 * @property int $security_level;
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
        if ($this->unrestricted) {
            return true;
        }

        return $this->accessRights()->where('name', $accessRightName)->exists();
    }

    public function accessRights()
    {
        return $this->belongsToMany(AccessRight::class, 'access_right_group', 'group_id', 'access_right_name', 'id', 'name');
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