<?php

namespace esc\Modules;

use esc\Models\Player;

class Checkpoint
{
    public $player;
    public $score;
    public $id;

    public function __construct(Player $player, int $score, int $id)
    {
        $this->player = $player;
        $this->score = $score;
        $this->id = $id;
    }

    public function givenTimeIsBetter(int $score): bool
    {
        return $score < $this->score;
    }
}