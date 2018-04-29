<?php

namespace esc\Models;

class Stats extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'stats';

    protected $fillable = ['Visits', 'LastPlayer', 'Finishes', 'Locals', 'Donations', 'Playtime', 'Wins', 'Player', 'Score', 'Rank', 'updated_at', 'created_at'];

    protected $primaryKey = 'Player';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function player()
    {
        return $this->belongsTo(Player::class, 'Player', 'id');
    }
}