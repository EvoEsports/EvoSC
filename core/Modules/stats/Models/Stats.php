<?php

class Stats extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'stats';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function player()
    {
        return $this->belongsTo(\esc\Models\Player::class, 'Player', 'id');
    }
}