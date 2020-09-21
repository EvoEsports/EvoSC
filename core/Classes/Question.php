<?php


namespace EvoSC\Classes;


use EvoSC\Models\Player;
use Illuminate\Support\Collection;

class Question
{
    private static Collection $questions;

    /**
     * @param string $question
     * @param Player $player
     * @param callable $callback
     */
    public static function ask(string $question, Player $player, callable $callback)
    {
        if (!isset(self::$questions)) {
            self::$questions = collect();
        }

        infoMessage($question)->send($player);

        $o = (object)[
            'target' => $player->id,
            'callback' => $callback
        ];

        self::$questions->put($player->id, $o);
    }

    /**
     * @return Collection
     */
    public static function getQuestions(): Collection
    {
        return self::$questions;
    }

    /**
     * @param $playerId
     */
    public static function forget($playerId)
    {
        self::$questions->forget($playerId);
    }
}