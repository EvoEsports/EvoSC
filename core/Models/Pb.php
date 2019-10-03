<?php


namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class Pb
 * @package esc\Models
 *
 * @property int map_id;
 * @property int player_id;
 * @property int score;
 * @property int checkpoints;
 */
class Pb extends Model
{
    protected $table = 'pbs';

    protected $fillable = [
        'map_id', 'player_id', 'score', 'checkpoints'
    ];
}