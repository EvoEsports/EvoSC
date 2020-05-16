<?php


namespace EvoSC\Modules\MapList\Models;


use Illuminate\Database\Eloquent\Model;

class MapFavorite extends Model
{
    protected $table = 'map-favorites';

    protected $fillable = [
        'player_id',
        'map_id',
    ];

    public $timestamps = false;
}