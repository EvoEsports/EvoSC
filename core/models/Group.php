<?php

namespace esc\models;


use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    const SUPER = 1;
    const ADMIN = 2;
    const MOD = 3;
    const PLAYER = 4;
}