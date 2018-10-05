<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\PredefinedVotes;
use esc\Classes\Template;
use esc\Classes\Vote;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Models\Player;

class Votes extends PredefinedVotes
{
    private static $activeVote;

    public function __construct()
    {
        ChatController::addCommand('res', [self::class, 'askMoreTime'], 'Ask for more time');
        ChatController::addCommand('skip', [self::class, 'askSkip'], 'Ask to skip map');

        Hook::add('CycleFinished', [self::class, 'checkCurrentVote']);

        KeyController::createBind('F5', [self::class, 'voteYes']);
        KeyController::createBind('F6', [self::class, 'voteNo']);
    }

    public static function voteInProgress(): bool
    {
        return self::$activeVote != null;
    }

    public static function getCurrentVote(): ?Vote
    {
        return self::$activeVote;
    }

    public static function setVote(Vote $vote)
    {
        self::$activeVote = $vote;
    }

    public static function checkCurrentVote()
    {
        if (!self::voteInProgress()) {
            return;
        }

        $vote = self::getCurrentVote();

        if ($vote->voteFinished()) {
            if ($vote->isSuccessfull()) {
                ChatController::message(onlinePlayers(), '_info', 'Vote was successful.');
                $vote->execute();
            } else {
                ChatController::message(onlinePlayers(), '_info', 'Vote was not successful.');
            }

            self::$activeVote = null;
        }
    }

    public static function showWidget(Vote $vote)
    {
        Template::showAll('votes.vote', compact('vote'));
    }

    public static function voteYes(Player $player)
    {
        if (!self::voteInProgress()) {
            return;
        }

        $vote = self::getCurrentVote();
        $vote->vote($player, true);
        self::showWidget($vote);
    }

    public static function voteNo(Player $player)
    {
        if (!self::voteInProgress()) {
            return;
        }

        $vote = self::getCurrentVote();
        $vote->vote($player, false);
        self::showWidget($vote);
    }
}