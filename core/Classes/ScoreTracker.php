<?php


namespace EvoSC\Classes;


use EvoSC\Models\Player;

class ScoreTracker
{
    /**
     * @var Player
     */
    public Player $player;

    public string $best_checkpoints;
    public string $last_checkpoints;
    public int $best_score;
    public int $last_score;

    public int $points = 0;
    public int $last_points_received = 0;

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