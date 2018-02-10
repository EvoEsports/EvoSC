<?php

namespace esc\models;


use esc\controllers\PlayerController;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';

    protected $fillable = ['Login', 'NickName', 'LadderScore'];

    protected $primaryKey = 'Login';

    public $incrementing = false;

    public $timestamps = false;

    public $spectator = false;
    public $afk = false;
    public $score = 0;
    private $online = false;

    public function nick($plain = false)
    {
        return $this->NickName;
    }

    public function getTime(bool $asMilliseconds = false)
    {
        if ($asMilliseconds) {
            return $this->score;
        }

        $seconds = floor($this->score / 1000);
        $ms = $this->score - ($seconds * 1000);
        $minutes = floor($seconds / 60);
        $seconds -= $minutes * 60;

        return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
    }

    public function setScore($score)
    {
        $this->score = $score;
    }

    public static function exists(string $login)
    {
        $player = self::whereLogin($login)->first();
        return $player != null;
    }

    public function setIsSpectator(bool $isSpectator)
    {
        $this->spectator = $isSpectator;
    }

    public function isSpectator(): bool
    {
        return $this->spectator > 0;
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function setOnline()
    {
        $this->online = true;
    }

    public function setOffline()
    {
        $this->online = false;
    }

    public function hasFinished(): bool
    {
        return $this->score > 0;
    }

    public function locals()
    {
        return $this->hasMany('esc\models\LocalRecord', 'player');
    }
}