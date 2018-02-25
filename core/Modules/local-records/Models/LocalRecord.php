<?php

use esc\classes\Timer;

class LocalRecord extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'local-records';

    protected $fillable = ['Player', 'Map', 'Score', 'Rank'];

    public $timestamps = false;

    public function player()
    {
        return $this->hasOne('\esc\models\Player', 'id', 'Player');
    }

    public function getPlayer(): ?\esc\models\Player
    {
        return $this->player()->first();
    }

    public function getScore(): string
    {
        return formatScore($this->Score);
    }
}