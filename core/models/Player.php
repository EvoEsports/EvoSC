<?php

namespace esc\models;


use esc\classes\Timer;
use esc\controllers\PlayerController;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';
    protected $fillable = ['Login', 'NickName', 'Score', 'Online', 'Afk', 'Spectator'];
    protected $primaryKey = 'Login';
    public $incrementing = false;
    public $timestamps = false;

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
            return $this->Score ?: 0;
        }

        return formatScore($this->Score ?: 0);
    }

    /**
     * Sets the current time of the player
     * @param $score
     */
    public function setScore($score)
    {
        $this->update(['Score' => $score]);
    }

    /**
     * Checks if a player exists by login
     * @param string $login
     * @return bool
     */
    public static function exists(string $login)
    {
        $player = self::whereLogin($login)->first();
        return $player != null;
    }

    /**
     * Set spectator status
     * @param bool $isSpectator
     * @return Player
     */
    public function setIsSpectator(bool $isSpectator): Player
    {
        $this->update(['Spectator' => $isSpectator]);
        return $this;
    }

    /**
     * Get spectator status
     * @return bool
     */
    public function isSpectator(): bool
    {
        return $this->Spectator;
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
        return $this->Score > 0;
    }

    /**
     * Get players locals
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function locals()
    {
        return $this->hasMany('esc\models\LocalRecord', 'Player', 'id');
    }

    public function group()
    {
        return $this->hasOne('esc\models\Group', 'id', 'Group');
    }

    public function hasGroup(array $groups)
    {
        return in_array($this->Group, $groups);
    }

    public function dedis()
    {
        return $this->hasMAny('Dedi', 'Player', 'id');
    }

    public function isSuperadmin(): bool
    {
        return $this->hasGroup([Group::SUPER]);
    }

    public function isAdmin(): bool
    {
        return $this->hasGroup([Group::ADMIN, Group::SUPER]);
    }

    public function isModerator(): bool
    {
        return $this->hasGroup([Group::MOD]);
    }

    public function isPlayer(): bool
    {
        return $this->hasGroup([Group::PLAYER]);
    }

    public static function console(): Player
    {
        $player = new Player();
        $player->Login = 'console';
        $player->NickName = 'console';
        $player->group = Group::find(1);
        return $player;
    }
}