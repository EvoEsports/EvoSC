<?php

namespace esc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Stats
 *
 * @package esc\Models
 *
 * @property integer Visits
 * @property  integer Rank
 *
 */
class Stats extends Model
{
    protected $table = 'stats';

    protected $fillable = [
        'Visits',
        'LastPlayer',
        'Finishes',
        'Locals',
        'Donations',
        'Playtime',
        'Wins',
        'Player',
        'Score',
        'Rank',
        'updated_at',
        'created_at'
    ];

    protected $primaryKey = 'Player';

    /**
     * @return BelongsTo
     */
    public function player()
    {
        return $this->belongsTo(Player::class, 'Player', 'id');
    }
}