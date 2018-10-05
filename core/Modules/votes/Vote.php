<?php

namespace esc\Classes;


use esc\Controllers\ChatController;
use esc\Models\Player;
use Illuminate\Support\Collection;

class Vote
{
    public $question;
    public $voters;
    public $duration;
    public $callback;
    public $arguments;
    public $voteId;

    private $startTime;

    public function __construct(string $question, array $callback, ...$arguments)
    {
        $this->startTime = time();

        $this->voters    = collect();
        $this->callback  = $callback;
        $this->duration  = 30;
        $this->voteId    = uniqid();
        $this->question  = $question;
        $this->arguments = $arguments;

        ChatController::message(onlinePlayers(false), '_info', 'A vote started: ', secondary($question));
    }

    public function allPlayersVoted(): bool
    {
        return $this->voters && $this->voters->count() == onlinePlayers(false)->count();
    }

    public function secondsLeft(): int
    {
        return $this->startTime + $this->duration - time();
    }

    public function voteFinished(): bool
    {
        if ($this->allPlayersVoted()) {
            // return true;
        }

        return $this->secondsLeft() <= 0;
    }

    public function getYesVoters(): Collection
    {
        return $this->voters->filter(function ($value) {
            return $value == true;
        });
    }

    public function getNoVoters(): Collection
    {
        return $this->voters->filter(function ($value) {
            return $value == false;
        });
    }

    public function isSuccessfull(): bool
    {
        $yesVoters = self::getYesVoters();
        $noVoters  = self::getNoVoters();

        return $yesVoters->count() > $noVoters->count();
    }

    public function execute()
    {
        try {
            if (is_callable($this->callback, false, $callableName)) {
                call_user_func($this->callback, ...$this->arguments);
                Log::logAddLine('Hook', "Execute: " . $this->callback[0] . " " . $this->callback[1], false);
            } else {
                throw new \Exception("Function call invalid, must use: [ClassName, ClassFunctionName]");
            }
        } catch (\Exception $e) {
            Log::logAddLine('Votes', "Exception: " . $e->getMessage(), isVerbose());
            Log::logAddLine('Stack trace', $e->getTraceAsString(), isVerbose());
        } catch (\TypeError $e) {
            Log::logAddLine('Votes', "TypeError: " . $e->getMessage(), isVerbose());
            Log::logAddLine('Stack trace', $e->getTraceAsString(), isVerbose());
        }
    }

    public function vote(Player $player, bool $decision)
    {
        $this->voters->put($player->Login, $decision);
    }
}