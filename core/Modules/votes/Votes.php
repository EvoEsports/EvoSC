<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Models\Player;
use Illuminate\Support\Collection;

class Votes
{
    private static $vote;
    private static $voters;
    private static $lastVote;

    public function __construct()
    {
        self::$voters   = collect();
        self::$lastVote = now()->subSeconds(config('votes.cooldown'));

        ChatController::addCommand('vote', [self::class, 'startVoteQuestion'], 'Start a custom vote', '//', 'vote_custom');
        ChatController::addCommand('res', [self::class, 'askMoreTime'], 'Ask for more time');
        ChatController::addCommand('replay', [self::class, 'askMoreTime'], 'Ask for more time');
        ChatController::addCommand('time', [self::class, 'askMoreTime'], 'Ask for more time');
        ChatController::addCommand('skip', [self::class, 'askSkip'], 'Ask to skip map');
        ChatController::addCommand('y', [self::class, 'voteYes'], 'Vote yes');
        ChatController::addCommand('n', [self::class, 'voteNo'], 'Vote no');

        Hook::add('EndMatch', [self::class, 'endMatch']);

        KeyController::createBind('F5', [self::class, 'voteYes']);
        KeyController::createBind('F6', [self::class, 'voteNo']);

        if (config('quick-buttons.enabled')) {
            ManiaLinkEvent::add('vote.approve', [self::class, 'approveVote'], 'vote_decide');
            ManiaLinkEvent::add('vote.decline', [self::class, 'declineVote'], 'vote_decide');
            QuickButtons::addButton('', 'Approve vote', 'vote.approve', 'vote_decide');
            QuickButtons::addButton('', 'Decline vote', 'vote.decline', 'vote_decide');
        }
    }

    public static function startVote(Player $player, string $question, $action)
    {
        if (self::$vote != null) {
            warningMessage('There is already a vot ein progress.')->send($player);

            return;
        }

        self::$vote = collect([
            'question'   => $question,
            'start_time' => now(),
            'duration'   => config('votes.duration'),
            'action'     => $action,
        ]);

        Timer::create('vote.check_state', [self::class, 'checkVoteState'], '1s', true);

        $voteStateJson = '{"yes":0,"no":0}';
        Template::showAll('votes.update-vote', compact('voteStateJson'));

        Template::showAll('votes.vote', compact('question'));
    }

    public static function checkVoteState()
    {
        if (!self::$vote) {
            return;
        }

        if (now()->diffInSeconds(self::$vote['start_time']) > self::$vote['duration'] || self::$voters->count() == onlinePlayers()->count()) {
            Timer::destroy('vote.check_state');
            $action    = self::$vote['action'];
            $voteState = self::getVoteState();
            $action($voteState['yes'] > $voteState['no']);
            self::$vote    = null;
            self::$voters  = collect();
            $voteStateJson = '{"yes":-1,"no":-1}';
            Template::showAll('votes.update-vote', compact('voteStateJson'));
        }
    }

    public static function askMoreTime(Player $player)
    {
        $diffInSeconds = self::$lastVote->diffInSeconds();
        if ($diffInSeconds < config('votes.cooldown')) {
            $waitTime = config('votes.cooldown') - $diffInSeconds;
            warningMessage('There already was a vote recently, please ', secondary('wait ' . $waitTime . ' seconds'), ' before voting again.')->send($player);

            return;
        }

        self::startVote($player, 'Add 10 minutes?', function ($success) {
            if ($success) {
                infoMessage('Vote to add time was successful.')->sendAll();
                MapController::addTime(MapController::getTimeLimit());
            } else {
                infoMessage('Vote to add time was not successful.')->sendAll();
            }
        });

        self::$lastVote = now();

        infoMessage('A vote to ', secondary('add 10 minutes'), ' started. Use ', secondary('F5/F6'), ' or ', secondary('/y'), ', ', secondary('/n'), ' to vote.')->sendAll();
    }

    public static function startVoteQuestion(Player $player, string $cmd, ...$questionArray)
    {
        $question = implode(' ', $questionArray);

        self::startVote($player, $question, function (bool $success) use ($question) {
            infoMessage('Vote ', secondary($question), ' ended with ', secondary($success ? 'yes' : 'no'))->sendAll();
        });
    }

    public static function askSkip(Player $player)
    {
        $diffInSeconds = self::$lastVote->diffInSeconds();
        if ($diffInSeconds < config('votes.cooldown')) {
            $waitTime = config('votes.cooldown') - $diffInSeconds;
            warningMessage('There already was a vote recently, please ', secondary('wait ' . $waitTime . ' seconds'), ' before voting again.')->send($player);

            return;
        }

        self::startVote($player, 'Skip map?', function (bool $success) {
            if ($success) {
                infoMessage('Vote to skip map was successful.')->sendAll();
                MapController::skip();
            } else {
                infoMessage('Vote to skip map was not successful.')->sendAll();
            }
        });

        self::$lastVote = now();

        infoMessage('A vote to ', secondary('skip the map'), ' started. Use ', secondary('F5/F6'), ' or ', secondary('/y'), ', ', secondary('/n'), ' to vote.')->sendAll();
    }

    private static function getVoteState(): Collection
    {
        $yesVotes = self::$voters->filter(function ($vote) {
            return $vote == true;
        })->count();

        $noVotes = self::$voters->filter(function ($vote) {
            return $vote == false;
        })->count();

        return collect([
            'yes' => $yesVotes,
            'no'  => $noVotes,
        ]);
    }

    private static function updateVoteState()
    {
        $voteStateJson = self::getVoteState()->toJson();

        Template::showAll('votes.update-vote', compact('voteStateJson'));
    }

    public static function voteYes(Player $player)
    {
        self::$voters->put($player->Login, true);
        self::updateVoteState();
    }

    public static function voteNo(Player $player)
    {
        self::$voters->put($player->Login, false);
        self::updateVoteState();
    }

    public static function approveVote(Player $player)
    {
        Timer::destroy('vote.check_state');
        $action = self::$vote['action'];

        try {
            $action(true);
        } catch (\Error $e) {
            Log::logAddLine('Votes', $e->getMessage());
        }

        self::$vote   = null;
        self::$voters = collect();
        infoMessage($player, ' passes vote.')->sendAll();
        $voteStateJson = '{"yes":-1,"no":-1}';
        Template::showAll('votes.update-vote', compact('voteStateJson'));
    }

    public static function declineVote(Player $player)
    {
        Timer::destroy('vote.check_state');
        $action = self::$vote['action'];

        try {
            $action(false);
        } catch (\Error $e) {
            Log::logAddLine('Votes', $e->getMessage());
        }

        self::$vote   = null;
        self::$voters = collect();
        infoMessage($player, ' cancels vote.')->sendAll();
        $voteStateJson = '{"yes":-1,"no":-1}';
        Template::showAll('votes.update-vote', compact('voteStateJson'));
    }

    public static function endMatch()
    {
        if (self::$vote != null) {
            Timer::destroy('vote.check_state');
            $action = self::$vote['action'];

            try {
                $action(false);
            } catch (\Error $e) {
                Log::logAddLine('Votes', $e->getMessage());
            }

            self::$vote    = null;
            self::$voters  = collect();
            $voteStateJson = '{"yes":-1,"no":-1}';
            Template::showAll('votes.update-vote', compact('voteStateJson'));
            infoMessage('Vote cancelled.')->sendAll();
        }
    }
}