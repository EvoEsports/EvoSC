<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\PredefinedVotes;
use esc\Classes\Template;
use esc\Classes\Vote;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Models\Player;

class Votes extends PredefinedVotes
{
    private static $activeVote;

    public function __construct()
    {
        ChatController::addCommand('replay', [self::class, 'askMoreTime'], 'Ask for more time');
        ChatController::addCommand('time', [self::class, 'askMoreTime'], 'Ask for more time');
        ChatController::addCommand('res', [self::class, 'askMoreTime'], 'Ask for more time');
        ChatController::addCommand('skip', [self::class, 'askSkip'], 'Ask to skip map');

        Hook::add('CycleFinished', [self::class, 'checkCurrentVote']);
        Hook::add('EndMatch', [self::class, 'endMatch']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);

        KeyController::createBind('F5', [self::class, 'voteYes']);
        KeyController::createBind('F6', [self::class, 'voteNo']);

        ManiaLinkEvent::add('vote.approve', [self::class, 'approveVote']);
        ManiaLinkEvent::add('vote.decline', [self::class, 'declineVote']);

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Approve vote', 'vote.approve', 'vote');
            QuickButtons::addButton('', 'Decline vote', 'vote.decline', 'vote');
        }
    }

    public static function hideVote()
    {
        Template::showAll('votes.hide');
    }

    public static function approveVote(Player $player)
    {
        if (!self::voteInProgress()) {
            ChatController::message($player, '_info', 'There is not vote in progress.');

            return;
        }

        $vote = self::getCurrentVote();

        ChatController::message(onlinePlayers(false), '_info', $player, ' approves vote ', secondary($vote->question));
        $vote->execute();
        self::hideVote();
    }

    public static function declineVote(Player $player)
    {
        if (!self::voteInProgress()) {
            ChatController::message($player, '_info', 'There is not vote in progress.');

            return;
        }

        $vote = self::getCurrentVote();

        ChatController::message(onlinePlayers(false), '_info', $player, ' cancels vote ', secondary($vote->question));
        self::$activeVote = null;
        self::hideVote();
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

    public static function beginMatch()
    {
        self::$addTimeFailed = false;
    }

    public static function addTimeFailed(): bool
    {
        return self::$addTimeFailed;
    }

    public static function endMatch()
    {
        if (self::voteInProgress()) {
            ChatController::message(onlinePlayers(false), '_info', 'Vote ', secondary(self::$activeVote->question), ' was not successful.');
            self::$activeVote = null;
        }
    }

    public static function checkCurrentVote()
    {
        if (!self::voteInProgress()) {
            return;
        }

        $vote = self::getCurrentVote();

        if ($vote->voteFinished()) {
            if ($vote->isSuccessfull()) {
                ChatController::message(onlinePlayers(), '_info', 'Vote ', secondary($vote->question), ' was successful.');
                $vote->execute();
            } else {
                ChatController::message(onlinePlayers(false), '_info', 'Vote ', secondary($vote->question), ' was not successful.');
                if ($vote->question == 'Add 10 minutes playtime?') {
                    self::$addTimeFailed = true;
                }
            }

            self::hideVote();
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