<?php

use esc\Models\Map;
use esc\Models\Player;

class LocalRecord extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'local-records';

    protected $fillable = ['Player', 'Map', 'Score', 'Rank', 'Checkpoints'];

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