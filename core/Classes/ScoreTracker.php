<?php


namespace esc\Classes;


use esc\Models\Player;

class ScoreTracker
{
    /**
     * @var Player
     */
    public $player;

    public $best_checkpoints;
    public $last_checkpoints;
    public $best_score;
    public $last_score;

    public function __construct(Player $player, int $score, string $checkpoints)
    {
        $this->player = $player;
        $this->best_score = $score;
        $this->last_score = $score;
        $this->best_checkpoints = $checkpoints;
        $this->last_checkpoints = $checkpoints;
    }
}