<?php

namespace esc\models;


use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';

    protected $fillable = ['Login', 'NickName', 'LadderScore'];

    protected $primaryKey = 'Login';

    public $timestamps = false;

    public $spectator = false;
    public $afk = false;
    public $score = 0;

    private function plainNick(): string
    {
        return preg_replace('/\$[a-f\d]{3}|\$i|\$s|\$w|\$n|\$m|\$g|\$o|\$z|\$t/i', '', $this->NickName);
    }

    public function nick($plain = false)
    {
        if ($plain) {
            return $this->plainNick();
        }

        return $this->NickName;
    }

    public function getTime()
    {
        return $this->getTimeFormatted();
    }

    public function setScore($score)
    {
        $this->score = $score;
    }

    private function getTimeFormatted()
    {
        if ($this->spectator) {
            if ($this->afk) {
                return "AFK";
            }

            return "SPEC";
        }

        if ($this->score == 0) {
            return '-,---';
        }

        $minutes = 0;
        $seconds = floor($this->score / 1000);
        $ms = $this->score % 1000;

        if ($seconds >= 60) {
            $minutes = $seconds / 60;
            $seconds = $seconds % 60;
        }

        if ($minutes == 0) {
            return sprintf('%d.%03d', $seconds, $ms);
        }

        return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
    }

    public static function exists(string $login)
    {
        $player = self::whereLogin($login)->first();
        return $player->exists;
    }

    public function setIsSpectator(bool $isSpectator)
    {
        $this->spectator = $isSpectator;
    }

    public function isSpectator()
    {
        return $this->spectator;
    }

    public function locals(){
        return $this->hasMany('esc\models\LocalRecord', 'player');
    }
}