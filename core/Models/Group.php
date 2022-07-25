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

    /**
     * @var string
     */
    protected $table = 'groups';

    /**
     * @var string[]
     */
    protected $guarded = ['id'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @param string $accessRightName
     * @return bool
     */
    public function hasAccess(string $accessRightName)
    {
        if ($this->unrestricted || empty($accessRightName)) {
            return true;
        }

        return $this->accessRights()->where('name', $accessRightName)->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function accessRights()
    {
        return $this->belongsToMany(AccessRight::class, 'access_right_group', 'group_id', 'access_right_name', 'id', 'name');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function player()
    {
        return $this->hasMany(Player::class, 'Group');
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->color) {
            return '$' . $this->color . $this->Name;
        }

        return $this->Name;
    }
}