<?php

namespace esc\Models;

use Illuminate\Support\Collection;

class LocalRecord extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'local-records';

    protected $fillable = ['Player', 'Map', 'Score', 'Rank', 'Checkpoints'];

    protected $primaryKey = 'id';

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

    public function getCpsAttribute(): Collection
    {
        $cps = collect(explode(',', $this->Checkpoints))->transform(function($cp){
            return intval($cp);
        });

        return $cps;
    }

    public function __toString()
    {
        return secondary($this->Rank . '.$') . config('colors.local') . ' local record ' . secondary(formatScore($this->Score));
    }
}