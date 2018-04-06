<?php

namespace esc\Models;


use Dedi;
use Illuminate\Database\Eloquent\Model;
use LocalRecord;
use MxKarma;
use Stats;

class Player extends Model
{
    protected $table = 'players';
    protected $fillable = ['Login', 'NickName', 'Score', 'Online', 'Afk', 'Spectator', 'MaxRank', 'Banned'];
    protected $primaryKey = 'Login';
    public $incrementing = false;
    public $timestamps = false;

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
        return $this->hasMany(LocalRecord::class, 'Player');
    }

    public function dedis()
    {
        return $this->hasMany(Dedi::class, 'Player');
    }

    public function ratings()
    {
        return $this->hasMany(MxKarma::class, 'Player');
    }

    public function stats()
    {
        return $this->hasOne(Stats::class, 'Player', 'id');
    }

    public function group()
    {
        return $this->hasOne(Group::class, 'id', 'Group');
    }

    public function isMasteradmin(): bool
    {
        if (!$this->group) {
            return false;
        }

        return $this->group->id == 1;
    }

    public function isAdmin(): bool
    {
        if ($this->isMasteradmin()) {
            return true;
        }

        if (!$this->group) {
            return false;
        }

        return strtolower($this->group->Name) == 'admin';
    }

    public static function console(): Player
    {
        $player = new Player();
        $player->Login = config('server.name');
        $player->NickName = config('server.name');
        $player->Group = Group::find(1);
        return $player;
    }
}