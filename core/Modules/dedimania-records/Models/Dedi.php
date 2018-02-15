<?php

use esc\models\Player;

class Dedi
{
    public $rank;
    public $score;
    public $player;

    public function __construct(Player $player, int $score, int $rank)
    {
        $this->player = $player;
        $this->score = $score;
        $this->rank = $rank;
    }
}