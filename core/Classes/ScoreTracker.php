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

    public $points = 0;
    public $last_points_received = 0;

    public function __construct(Player $player, int $score, string $checkpoints)
    {
        $this->player = $player;
        $this->best_score = $score;
        $this->last_score = $score;
        $this->best_checkpoints = $checkpoints;
        $this->last_checkpoints = $checkpoints;
    }

    public function addPoints($n)
    {
        $this->last_points_received = $n;
        $this->points += $n;
    }
}