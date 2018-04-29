<?php

namespace esc\Models;

class Dedi extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'dedi-records';

    protected $fillable = ['Map', 'Player', 'Score', 'Rank', 'Checkpoints'];

    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function player()
    {
        return $this->belongsTo(Player::class, 'Player', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function map()
    {
        return $this->belongsTo(Map::class, 'Map', 'id');
    }

    /**
     * @return string
     */
    public function score(): string
    {
        return formatScore($this->Score);
    }
}