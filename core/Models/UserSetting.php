<?php

namespace EvoSC\Models;


use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $table = 'user-settings';

    protected $fillable = ['player_id', 'name', 'value'];

    protected $primaryKey = 'player_Login';

    public $timestamps = false;
}