<?php

namespace esc\models;


use esc\classes\Timer;
use esc\controllers\PlayerController;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';
    protected $fillable = ['Login', 'NickName', 'LastScore', 'Online'];
    protected $primaryKey = 'Login';
    public $incrementing = false;
    public $timestamps = false;

    public $spectator = false;
    public $afk = false;

    /**
     * Returns nickname of player, stripped of colors if true
     * @param bool $plain
     * @return mixed
     */
    public function nick($plain = false)
    {
        if ($plain) {
            return preg_replace('/\$[0-9a-f]{3}/', '', $this->NickName);
        }

        return $this->NickName;
    }

    /**
     * Gets the players current time (formatted)
     * @param bool $asMilliseconds
     * @return mixed|string
     */
    public function getTime(bool $asMilliseconds = false)
    {
        if ($asMilliseconds) {
            return $this->LastScore;
        }

        return Timer::formatScore($this->LastScore ?: 0);
    }

    /**
     * Sets the current time of the player
     * @param $score
     */
    public function setScore($score)
    {
        $this->update(['LastScore' => $score]);
    }

    public static function exists(string $login)
    {
        $player = self::whereLogin($login)->first();
        return $player != null;
    }

    /**
     * Set spectator status
     * @param bool $isSpectator
     */
    public function setIsSpectator(bool $isSpectator)
    {
        $this->spectator = $isSpectator;
    }

    /**
     * Get spectator status
     * @return bool
     */
    public function isSpectator(): bool
    {
        return $this->spectator > 0;
    }

    /**
     * Sets player online
     * @return Player
     */
    public function setOnline(): Player
    {
        $this->update(['Online' => true]);
        return $this;
    }

    /**
     * Sets player offline
     * @return Player
     */
    public function setOffline(): Player
    {
        $this->update(['Online' => false]);
        return $this;
    }

    /**
     * Checks if player finished
     * @return bool
     */
    public function hasFinished(): bool
    {
        return $this->LastScore > 0;
    }

    /**
     * Get players locals
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function locals()
    {
        return $this->hasMany('esc\models\LocalRecord', 'player');
    }

    public function group()
    {
        return $this->hasOne('esc\models\Group', 'id', 'Group');
    }

    public function hasGroup(array $groups)
    {
        return in_array($this->group->Name, $groups);
    }

    public function dedis()
    {
        return $this->hasMAny('Dedi', 'Player', 'id');
    }
}