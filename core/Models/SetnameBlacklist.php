<?php

namespace EvoSC\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $login
 * @property string|null $reason
 * @property string $blacklisted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property BelongsTo|Player $admin
 * @property BelongsTo|Player $player
 */
class SetnameBlacklist extends Model
{
    /**
     * @var string
     */
    protected $table = 'setname-blacklist';

    /**
     * @var string[]
     */
    protected $guarded = ['id'];

    /**
     * @return BelongsTo
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'blacklisted_by', 'Login');
    }

    /**
     * @return BelongsTo
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'login', 'Login');
    }
}