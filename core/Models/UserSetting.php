<?php

namespace EvoSC\Models;


use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    /**
     * @var string
     */
    protected $table = 'user-settings';

    /**
     * @var string
     */
    protected $primaryKey = 'player_Login';

    /**
     * @var string[]
     */
    protected $guarded = ['player_Login'];

    /**
     * @var bool
     */
    public $timestamps = false;
}