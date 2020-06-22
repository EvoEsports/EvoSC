<?php

namespace EvoSC\Modules\Dedimania\Models;

use EvoSC\Models\Map;
use EvoSC\Models\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Class Dedi
 * @package EvoSC\Models
 * @property Player $player
 * @property Map $map
 * @property string $ghost_replay
 * @property string $v_replay
 */
class Dedi extends Model
{
    const TABLE = 'dedi-records';

    protected $table = self::TABLE;

    protected $fillable = ['Map', 'Player', 'Score', 'Rank', 'Checkpoints', 'ghost_replay', 'v_replay', 'New'];

    public $timestamps = false;

    /**
     * @return BelongsTo
     */
    public function player()
    {
        return $this->belongsTo(Player::class, 'Player', 'id');
    }

    /**
     * @return BelongsTo
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

    public function getGhostReplayAttribute(): ?string
    {
        if (isset($this->ghost_replay) && $this->ghost_replay != null) {
            return ghost($this->ghost_replay);
        }

        return null;
    }

    public function getCpsAttribute(): Collection
    {
        return collect(explode(',', $this->Checkpoints))->transform(function($cp){
            return intval($cp);
        });
    }

    public function __toString()
    {
        return secondary($this->Rank.'.$').config('colors.dedi').' dedimania record '.secondary(formatScore($this->Score));
    }
}