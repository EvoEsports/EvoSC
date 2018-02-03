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

    public function getTime(bool $asSeconds = false)
    {
        if($asSeconds){
            return $this->score;
        }

        return $this->getTimeFormatted();
    }

    public function setScore($score)
    {
        $this->score = $score;
    }

    private function getTimeFormatted()
    {
        if ($this->score == 0) {
            $time = '0.00,000';
        }

        $minutes = 0;
        $seconds = floor($this->score / 1000);
        $ms = $this->score % 1000;

        if ($seconds >= 60) {
            $minutes = $seconds / 60;
            $seconds = $seconds % 60;
        }

        if ($minutes == 0) {
            $time = sprintf('%02d.%03d', $seconds, $ms);
        }else{
            $time = sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
        }

        return $time;
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

    public function isSpectator(): bool
    {
        return $this->spectator > 0;
    }

    public function locals(){
        return $this->hasMany('esc\models\LocalRecord', 'player');
    }
}