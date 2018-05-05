<?php

namespace esc\Models;


use esc\Modules\MxKarma\MxKarma;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';
    protected $fillable = ['Login', 'NickName', 'Score', 'Online', 'Afk', 'spectator_status', 'MaxRank', 'Banned'];
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
        return $this->hasMany(LocalRecord::class, 'Player', 'id');
    }

    public function dedis()
    {
        return $this->hasMany(Dedi::class, 'Player', 'id');
    }

    public function ratings()
    {
        return $this->hasMany(MxKarma::class, 'Player', 'id');
    }

    public function stats()
    {
        return $this->hasOne(Stats::class, 'Player', 'id');
    }

    public function group()
    {
        return $this->hasOne(Group::class, 'id', 'Group');
    }

    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    public function getSpectatorStatusAttribute($value)
    {
        $object = collect([]);
        $object->spectator = (bool)($value % 10);
        $object->temporarySpectator = (bool)(intval($value / 10) % 10);
        $object->pureSpectator = (bool)(intval($value / 100) % 10);
        $object->autoTarget = (bool)(intval($value / 1000) % 10);
        $object->currentTargetId = intval($value / 10000);

        return $object;
    }

    public function isSpectator(): bool
    {
        return $this->spectator_status->spectator ?? false;
    }

    public function setSetting($settingName, $value)
    {
        Player::whereLogin($this->Login)->update(["user_settings->$settingName" => $value]);

        $setting = $this->settings()->whereName($settingName)->first();

        if (is_bool($value)) {
            $value = $value ? 'True' : 'False';
        }
        if (is_float($value)) {
            $value = sprintf('%.1f', $value);
        }
        if (is_integer($value)) {
            $value = sprintf('%d', $value);
        }

        if ($setting) {
            $setting->update(['value' => $value]);
            return;
        }

        $this->settings()->create([
            'name' => $settingName,
            'value' => $value
        ]);
    }

    public function setting($settingName)
    {
        return $this->settings()->whereName($settingName)->first()->value;
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