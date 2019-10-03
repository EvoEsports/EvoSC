<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\CountdownController;
use esc\Controllers\MapController;
use esc\Models\AccessRight;
use esc\Models\Player;
use Illuminate\Support\Collection;

class Votes
{
    /**
     * @var Collection
     */
    private static $vote;

    /**
     * @var Collection
     */
    private static $voters;

    private static $lastTimeVote;
    private static $lastSkipVote;
    private static $timeVotesThisRound = 0;
    private static $skipVotesThisRound = 0;
    private static $onlinePlayersCount;

    public function __construct()
    {
        self::$voters = collect();
        self::$lastTimeVote = time() - config('votes.time.cooldown-in-seconds');
        self::$lastSkipVote = time() - config('votes.skip.cooldown-in-seconds');
        self::$timeVotesThisRound = ceil(CountdownController::getAddedSeconds() / (CountdownController::getOriginalTimeLimit() * (config('votes.time-multiplier') ?? 1.0)));

        AccessRight::createIfMissing('vote_custom', 'Create a custom vote. Enter question after command.');
        AccessRight::createIfMissing('vote_always', 'Allowed to always start a time or skip vote.');

        ChatCommand::add('//vote', [self::class, 'startVoteQuestion'], 'Start a custom vote.', 'vote_custom');
        ChatCommand::add('/skip', [self::class, 'askSkip'], 'Start a vote to skip map.');
        ChatCommand::add('/y', [self::class, 'voteYes'], 'Vote yes.');
        ChatCommand::add('/n', [self::class, 'voteNo'], 'Vote no.');
        ChatCommand::add('/time', [self::class, 'askMoreTime'], 'Start a vote to add 10 minutes.')
            ->addAlias('/replay')
            ->addAlias('/restart')
            ->addAlias('/res');

        Hook::add('EndMatch', [self::class, 'endMatch']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);

        KeyBinds::add('vote_yes', 'Vote yes in a vote.', [self::class, 'voteYes'], 'F5');
        KeyBinds::add('vote_no', 'Vote no in a vote.', [self::class, 'voteNo'], 'F6');

        ManiaLinkEvent::add('votes.yes', [self::class, 'voteYes']);
        ManiaLinkEvent::add('votes.no', [self::class, 'voteNo']);

        if (config('quick-buttons.enabled')) {
            ManiaLinkEvent::add('vote.approve', [self::class, 'approveVote'], 'vote_decide');
            ManiaLinkEvent::add('vote.decline', [self::class, 'declineVote'], 'vote_decide');
            QuickButtons::addButton('', 'Approve vote', 'vote.approve', 'vote_decide');
            QuickButtons::addButton('', 'Decline vote', 'vote.decline', 'vote_decide');
        }
    }

    public static function startVote(Player $player, string $question, $action, $successRatio = 0.5): bool
    {
        if (self::$vote != null) {
            warningMessage('There is already a vote in progress.')->send($player);

            return false;
        }

        $secondsLeft = CountdownController::getSecondsLeft();

        if ($secondsLeft < 10) {
            warningMessage('Sorry, it is too late to start a vote.')->send($player);

            return false;
        }

        $duration = config('votes.duration');

        if ($secondsLeft <= $duration) {
            $duration = $secondsLeft - 4;
        }

        self::$onlinePlayersCount = onlinePlayers()->count();
        self::$voters = collect();
        self::$lastTimeVote = time();
        self::$vote = collect([
            'question' => $question,
            'success_ratio' => $successRatio,
            'start_time' => time(),
            'duration' => $duration,
            'action' => $action,
        ]);

        Timer::create('vote.check_state', [self::class, 'checkVoteState'], '1s', true);

        $voteStateJson = '{"yes":0,"no":0}';
        Template::showAll('votes.update-vote', compact('voteStateJson'));

        Template::showAll('votes.vote', compact('question', 'duration'));

        return true;
    }

    public static function checkVoteState(): int
    {
        if (!self::$vote) {
            return 0;
        }

        $voteCount = self::$voters->count();
        $timerRanOut = (time() - self::$vote['start_time']) > self::$vote['duration'];
        $everyoneVoted = $voteCount == self::$onlinePlayersCount;

        $voteState = self::getVoteState();
        $voteRatioReached = ($voteState['yes'] / self::$onlinePlayersCount) > self::$vote['success_ratio'];

        if ($timerRanOut || $everyoneVoted || $voteRatioReached) {
            if ($voteRatioReached) {
                $success = true;
            } else {
                $success = ($voteState['yes'] / $voteCount) > self::$vote['success_ratio'];
            }

            Timer::destroy('vote.check_state');
            $action = self::$vote['action'];
            $action($success);
            self::$vote = null;
            self::$voters = collect();
            $voteStateJson = '{"yes":-1,"no":-1}';
            Template::showAll('votes.update-vote', compact('voteStateJson'));

            return 1;
        }

        return 0;
    }

    public static function askMoreTime(Player $player, string $time = '0')
    {
        if (self::$timeVotesThisRound >= config('votes.time.limit-votes') && !$player->hasAccess('vote_always')) {
            warningMessage('The maximum time-vote-limit is reached, sorry.')->send($player);

            return;
        }

        $diffInSeconds = self::getSecondsSinceLastTimeVote();
        if ($diffInSeconds < config('votes.time.cooldown-in-seconds') && !$player->hasAccess('vote_always')) {
            $waitTime = config('votes.time.cooldown-in-seconds') - $diffInSeconds;
            warningMessage('There already was a vote recently, please ', secondary('wait '.$waitTime.' seconds'),
                ' before voting again.')->send($player);

            return;
        }

        $time = floatval($time);

        if($time > 0){
            $secondsToAdd = floatval($time) * 60;
        }else{
            $secondsToAdd = CountdownController::getOriginalTimeLimit() * config('votes.time-multiplier');
        }
        $question = 'Add '.round($secondsToAdd / 60, 1).' minutes?';

        $voteStarted = self::startVote($player, $question, function ($success) use ($secondsToAdd, $question) {
            if ($success) {
                infoMessage('Vote ', secondary($question), ' was successful.')->sendAll();
                CountdownController::addTime($secondsToAdd);
            } else {
                infoMessage('Vote ', secondary($question), ' did not pass.')->sendAll();
            }
        }, config('votes.time.success-ratio'));

        if ($voteStarted) {
            self::$lastTimeVote = time();
            self::$timeVotesThisRound++;

            infoMessage($player, ' started a vote to ', secondary('add '.round($secondsToAdd / 60, 1).' minutes?'),
                '. Use ', secondary('F5/F6'), ' and ', secondary('/y'), ' or ', secondary('/n'),
                ' to vote.')->setIcon('')->sendAll();
        }
    }

    public static function startVoteQuestion(Player $player, string $cmd, ...$questionArray)
    {
        $question = implode(' ', $questionArray);

        self::startVote($player, $question, function (bool $success) use ($question) {
            infoMessage('Vote ', secondary($question), ' ended with ',
                secondary($success ? 'yes' : 'no'))->setIcon('')->sendAll();
        });
    }

    public static function askSkip(Player $player)
    {
        $secondsPassed = CountdownController::getSecondsLeft();

        if (!$player->hasAccess('vote_always')) {
            if ($secondsPassed < config('votes.skip.cooldown-in-seconds')) {
                warningMessage('Please wait ',
                    secondary((config('votes.skip.cooldown-in-seconds') - $secondsPassed).' seconds'),
                    ' before asking to skip the map.')->send($player);

                return;
            }

            if (self::$skipVotesThisRound >= config('votes.skip.limit-votes')) {
                warningMessage('The maximum of skip votes was reached, sorry.')->send($player);

                return;
            }

            $diffInSeconds = self::getSecondsSinceLastSkipVote();
            if ($diffInSeconds < config('votes.skip.cooldown-in-seconds')) {
                $waitTime = config('votes.skip.cooldown-in-seconds') - $diffInSeconds;
                warningMessage('There already was a vote recently, please ', secondary('wait '.$waitTime.' seconds'),
                    ' before voting again.')->send($player);

                return;
            }
        }

        $voteStarted = self::startVote($player, 'Skip map?', function (bool $success) {
            if ($success) {
                infoMessage('Vote to skip map was successful.')->sendAll();
                MapController::skip();
            } else {
                infoMessage('Vote to skip map was not successful.')->sendAll();
            }
        }, config('votes.skip.success-ratio'));

        if ($voteStarted) {
            self::$lastSkipVote = time();
            self::$skipVotesThisRound++;

            infoMessage($player, ' started a vote to ', secondary('skip the map'), '. Use ', secondary('F5/F6'),
                ' and ',
                secondary('/y'), ' or ', secondary('/n'), ' to vote.')->setIcon('')->sendAll();
        }
    }

    private static function getVoteState(): Collection
    {
        $yesVotes = self::$voters->whereStrict('decision', 'true')->count();
        $noVotes = self::$voters->whereStrict('decision', 'false')->count();

        return collect([
            'yes' => $yesVotes,
            'no' => $noVotes,
        ]);
    }

    private static function updateVoteState()
    {
        $voteStateJson = self::getVoteState()->toJson();

        Template::showAll('votes.update-vote', compact('voteStateJson'));
    }

    public static function voteYes(Player $player)
    {
        $vote = new \stdClass();
        $vote->player = $player->Login;
        $vote->decision = 'true';
        self::$voters->put($player->Login, $vote);

        self::updateVoteState();
        self::checkVoteState();
    }

    public static function voteNo(Player $player)
    {
        $vote = new \stdClass();
        $vote->player = $player->Login;
        $vote->decision = 'false';
        self::$voters->put($player->Login, $vote);

        self::updateVoteState();
        self::checkVoteState();
    }

    public static function playerConnect(Player $player)
    {
        if (!self::$vote) {
            return;
        }

        $vote = new \stdClass();
        $vote->player = $player->Login;
        $vote->decision = null;
        self::$voters->put($player->Login, $vote);
        self::$onlinePlayersCount++;
    }

    public static function playerDisconnect(Player $player)
    {
        if (!self::$vote) {
            return;
        }

        self::$voters->forget($player->Login);
        self::$onlinePlayersCount--;

        self::updateVoteState();
        self::checkVoteState();
    }

    public static function approveVote(Player $player)
    {
        Timer::destroy('vote.check_state');
        $action = self::$vote['action'];

        try {
            $action(true);
        } catch (\Error $e) {
            Log::write($e->getMessage());
        }

        self::$vote = null;
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
            Log::write($e->getMessage());
        }

        self::$vote = null;
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
                Log::write($e->getMessage());
            }

            $voteStateJson = '{"yes":-1,"no":-1}';
            Template::showAll('votes.update-vote', compact('voteStateJson'));
            infoMessage('Vote cancelled.')->setIcon('')->sendAll();
        }

        self::$vote = null;
        self::$voters = collect();
    }

    public static function beginMatch()
    {
        self::$timeVotesThisRound = 0;
        self::$skipVotesThisRound = 0;
    }

    private static function getSecondsSinceLastTimeVote()
    {
        return time() - self::$lastTimeVote;
    }

    private static function getSecondsSinceLastSkipVote()
    {
        return time() - self::$lastSkipVote;
    }
}