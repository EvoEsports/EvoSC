<?php

namespace EvoSC\Models;


use Carbon\Carbon;
use EvoSC\Controllers\UserSettingsController;
use EvoSC\Modules\Dedimania\Models\Dedi;
use EvoSC\Modules\MxKarma\Models\Karma;
use EvoSC\Modules\Statistics\Models\Stats;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Class Player
 *
 * @package EvoSC\Models
 * @property int $id
 * @property string $Login
 * @property string $NickName
 * @property string $ubisoft_name
 * @property mixed $Score
 * @property integer $player_id
 * @property boolean $Afk
 * @property string $path
 * @property string $spectator_status
 * @property integer $MaxRank
 * @property integer $team
 * @property boolean $banned
 * @property carbon $last_visit
 * @property Group $group
 *
 * @method static find(string $login)
 */
class Player extends Model
{
    protected $table = 'players';

    protected $fillable = [
        'Login',
        'NickName',
        'Score',
        'player_id',
        'Afk',
        'path',
        'spectator_status',
        'MaxRank',
        'banned',
        'last_visit',
        'Group',
    ];

    protected $primaryKey = 'Login';

    public $incrementing = false;

    public $timestamps = false;

    protected $dates = ['last_visit'];

    /**
     * Gets the players current time (formatted)
     *
     * @param boolean $asMilliseconds
     *
     * @return mixed|string
     */
    public function getTime(bool $asMilliseconds = false)
    {
        if ($asMilliseconds) {
            return $this->Score ?: 0;
        }

        return formatScore($this->Score ?: 0);
    }

    /**
     * Sets the current time of the player
     *
     * @param $score
     */
    public function setScore($score)
    {
        $this->update(['Score' => $score]);
    }

    /**
     * Checks if a player exists by login
     *
     * @param string $login
     *
     * @return bool
     */
    public static function exists(string $login)
    {
        $player = self::whereLogin($login)->first();

        return $player != null;
    }

    /**
     * Checks if player finished
     *
     * @return bool
     */
    public function hasFinished(): bool
    {
        return $this->Score > 0;
    }

    /**
     * @param string $accessRightId
     * @throws \EvoSC\Exceptions\UnauthorizedException
     */
    public function authorize(string $accessRightId)
    {
        if (!$this->hasAccess($accessRightId)) {
            list($childClass, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            throw new UnauthorizedException("$this is not authorized to call " . $caller['class'] . $caller['type'] . $caller['function']);
        }
    }

    /**
     * Get player location with $player->path
     *
     * @param $path
     *
     * @return string
     */
    public function getPathAttribute($path)
    {
        $parts = explode('|', $path);

        while (in_array($parts[0], ['World', 'Europe', 'Asia', 'North America', 'South America'])) {
            array_shift($parts);
        }

        if (count($parts) > 1) {
            array_pop($parts);
        }

        return implode(', ', $parts);
    }

    /**
     * Get players dedis, like locals.
     *
     * @return HasMany
     */
    public function dedis()
    {
        return $this->hasMany(Dedi::class, 'Player', 'id');
    }

    /**
     * Get players ratings, like locals.
     *
     * @return HasMany
     */
    public function ratings()
    {
        return $this->hasMany(Karma::class, 'Player', 'id');
    }

    /**
     * Get the statistics of that player
     *
     * @return HasOne
     */
    public function stats()
    {
        return $this->hasOne(Stats::class, 'Player', 'id');
    }

    /**
     * Get the player group
     *
     * @return HasOne
     */
    public function group()
    {
        return $this->hasOne(Group::class, 'id', 'Group');
    }

    /**
     * Get the players favorite maps.
     *
     * @return BelongsToMany
     */
    public function favorites()
    {
        return $this->belongsToMany(Map::class, 'map-favorites');
    }

    /**
     * Get the players user settings.
     *
     * @return HasMany
     */
    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * Check if the player has a specific access-right.
     *
     * @param string $right
     *
     * @return bool
     */
    public function hasAccess(string $right): bool
    {
        if (!$this->group) {
            return false;
        }

        return $this->group->hasAccess($right);
    }

    /**
     * Get spectator information about the player.
     *
     * @param $value
     *
     * @return Collection
     */
    /*
    public function getSpectatorStatusAttribute($value)
    {
        $object                     = collect([]);
        $object->spectator          = (bool)($value % 10);
        $object->temporarySpectator = (bool)(intval($value / 10) % 10);
        $object->pureSpectator      = (bool)(intval($value / 100) % 10);
        $object->autoTarget         = (bool)(intval($value / 1000) % 10);
        $object->currentTargetId    = intval($value / 10000);

        return $object;
    }
    */

    /**
     * Check if the player is in spectator-mode.
     *
     * @return bool
     */
    public function isSpectator(): bool
    {
        return $this->spectator_status > 0;
    }

    /**
     * Update a user-setting.
     *
     * @param string $settingName
     * @param mixed $value
     */
    public function setSetting($settingName, $value)
    {
        UserSettingsController::saveUserSetting($this, $settingName, $value);
    }

    /**
     * Get a user-setting.
     *
     * @param string $settingName
     *
     * @param bool $jsonDecode
     * @return mixed|null
     */
    public function setting($settingName, $jsonDecode = false)
    {
        $setting = $this->settings()->whereName($settingName)->first();

        if ($jsonDecode) {
            return json_decode($setting->value ?? null);
        }

        return $setting->value ?? null;
    }

    /**
     * Get last visit as Carbon object.
     *
     * @param string $date
     *
     * @return Carbon
     * @throws Exception
     */
    public function getLastVisitAttribute($date): Carbon
    {
        return new Carbon($date);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return trim($this->NickName);
    }

    public function getNameAttribute()
    {
        return preg_replace('/(?<![$])\${1}(([lm])(?:\[.+?]))/i', '', $this->NickName);
    }

    public function getNameBlankAttribute()
    {
        return preg_replace('/(?<![$])\${1}(([lm])(?:\[.+?])|[iwngosz]{1}|[\w\d]{1,3})/i', '', $this->NickName);
    }
}