<?php

namespace esc\models;


use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';

    protected $fillable = ['NickName', 'LadderScore'];

    public function plainNick()
    {
        return preg_replace('/\$[a-f\d]{3}|\$i|\$s|\$w|\$n|\$m|\$g|\$o|\$z|\$t/i', '', $this->NickName);
    }
}