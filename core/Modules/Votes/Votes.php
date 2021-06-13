<?php

namespace EvoSC\Modules\Votes;


use Error;
use EvoSC\Classes\Cache;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Classes\Timer;
use EvoSC\Controllers\CountdownController;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\PointsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\QuickButtons\QuickButtons;
use Illuminate\Support\Collection;
use stdClass;

class Votes extends Module implements ModuleInterface
{
    private static $vote;
    private static Collection $voters;

    private static $lastTimeVote;
    private static $lastSkipVote;
    private static $timeVotesThisRound = 0;
    private static int $skipVotesThisRound = 0;
    private static $onlinePlayersCount;
    private static $addTimeSuccess = null;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('//vote', [self::class, 'startVoteQuestion'], 'Start a custom vote.', 'vote_custom');
        ChatCommand::add('/skip', [self::class, 'askSkip'], 'Start a vote to skip map.');
        ChatCommand::add('/y', [self::class, 'voteYes'], 'Vote yes.');
        ChatCommand::add('/n', [self::class, 'voteNo'], 'Vote no.');
        ChatCommand::add('/res', [self::class, 'cmdAskMoreTime'], 'Start a vote to add or remove time/points.')
            ->addAlias('/replay')
            ->addAlias('/restart')
            ->addAlias('/points')
            ->addAlias('/time');

        Hook::add('EndMatch', [self::class, 'endMatch']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);

        InputSetup::add('vote_yes', 'Vote yes in a vote.', [self::class, 'voteYes'], 'F5');
        InputSetup::add('vote_no', 'Vote no in a vote.', [self::class, 'voteNo'], 'F6');

        ManiaLinkEvent::add('votes.yes', [self::class, 'voteYes']);
        ManiaLinkEvent::add('votes.no', [self::class, 'voteNo']);

        AccessRight::add('vote_decide', 'Decide the outcome of a vote.');

        if (config('quick-buttons.enabled')) {
            ManiaLinkEvent::add('vote.approve', [self::class, 'approveVote'], 'vote_decide');
            ManiaLinkEvent::add('vote.decline', [self::class, 'declineVote'], 'vote_decide');
            QuickButtons::addButton('', 'Approve vote', 'vote.approve', 'vote_decide');
            QuickButtons::addButton('', 'Decline vote', 'vote.decline', 'vote_decide');
        }
    }

    public function stop()
    {
        $data = (object)[
            'vote' => self::$vote,
            'voters' => self::$voters,
            'lastTimeVote' => self::$lastTimeVote,
            'lastSkipVote' => self::$lastSkipVote,
            'timeVotesThisRound' => self::$timeVotesThisRound,
            'skipVotesThisRound' => self::$skipVotesThisRound,
            'onlinePlayersCount' => self::$onlinePlayersCount,
            'addTimeSuccess' => self::$addTimeSuccess
        ];

        Cache::put('vote-cache', $data, now()->addMinutes(2));
    }

    public function __construct()
    {
        self::$voters = collect();

        if (Cache::has('vote-cache')) {
            $data = Cache::get('vote-cache');

            self::$lastTimeVote = $data->lastTimeVote;
            self::$lastSkipVote = $data->lastSkipVote;
        } else {
            self::$lastTimeVote = time() - config('votes.time.cooldown-in-seconds');
            self::$lastSkipVote = time() - config('votes.skip.cooldown-in-seconds');
            $originalTimeLimit = CountdownController::getOriginalTimeLimitInSeconds();
            self::$timeVotesThisRound = ceil(CountdownController::getAddedSeconds() / $originalTimeLimit);
        }

        AccessRight::add('vote_custom', 'Create a custom vote. Enter question after command.');
        AccessRight::add('no_vote_limits', 'Not bound to any limitation.');
    }

    public static function startVote(Player $player, string $question, $action, $successRatio = 0.5): bool
    {
        if (isset(self::$vote)) {
            warningMessage('There is already a vote in progress.')->send($player);

            return false;
        }

        $secondsLeft = CountdownController::getSecondsLeft();
        $duration = config('votes.duration');

        if ($secondsLeft && $secondsLeft <= $duration) {
            if ($secondsLeft < 15) {
                warningMessage('It is too late to start a vote.')->send($player);

                return false;
            }

            $duration = $secondsLeft - 3;
        }

        self::$onlinePlayersCount = onlinePlayers()->count();
        self::$voters = collect();
        self::$lastTimeVote = time();
        $vote = (object)[
            'question' => $question,
            'success_ratio' => $successRatio,
            'start_time' => time(),
            'duration' => $duration,
            'action' => $action,
        ];
        self::$vote = $vote;

        Timer::create('vote.check_state', [self::class, 'checkVoteState'], '1s', true);

        $voteStateJson = '{"yes":0,"no":0}';
        Template::showAll('Votes.update-vote', compact('voteStateJson'));

        Template::showAll('Votes.vote', compact('question', 'duration', 'vote'));

        return true;
    }

    public static function checkVoteState(): int
    {
        if (!self::$vote) {
            return 0;
        }

        $voteCount = self::$voters->count();
        $timerRanOut = (time() - self::$vote->start_time) > self::$vote->duration;
        $everyoneVoted = $voteCount == self::$onlinePlayersCount;

        $voteState = self::getVoteState();
        $voteRatioReached = ($voteState['yes'] / self::$onlinePlayersCount) > self::$vote->success_ratio;

        if ($timerRanOut || $everyoneVoted || $voteRatioReached) {
            $success = false;
            if ($voteRatioReached) {
                $success = true;
            } else if ($voteCount > 0) {
                $success = ($voteState['yes'] / $voteCount) > self::$vote->success_ratio;
            }

            Timer::destroy('vote.check_state');
            $action = self::$vote->action;
            $action($success);
            self::$vote = null;
            self::$voters = collect();
            $voteStateJson = '{"yes":-1,"no":-1}';
            Template::showAll('Votes.update-vote', compact('voteStateJson'));

            return 1;
        }

        return 0;
    }

    /**
     * @param Player $player
     * @param $cmd
     * @param string $time
     */
    public static function cmdAskMoreTime(Player $player, $cmd, $time = '0')
    {
        if (ModeController::isRoundsType()) {
            self::cmdAskMorePoints($player, $cmd, $time);
            return;
        }

        if (floatval($time) == 0) {
            $secondsToAdd = CountdownController::getOriginalTimeLimitInSeconds() * config('votes.time-multiplier');
        } else {
            $secondsToAdd = floatval($time) * 60;
        }

        if (!$player->hasAccess('no_vote_limits')) {
            $diffInSeconds = self::getSecondsSinceLastTimeVote();
            if ($diffInSeconds < config('votes.time.cooldown-in-seconds')) {
                $waitTime = config('votes.time.cooldown-in-seconds') - $diffInSeconds;
                warningMessage('There already was a vote recently, please ', secondary('wait ' . $waitTime . ' seconds'),
                    ' before voting again.')->send($player);
                return;
            }

            if ($secondsToAdd < 0) {
                warningMessage('Sorry, you\'re not allowed to reduce time.')->send($player);
                return;
            }

            $oSecondsToAdd = CountdownController::getOriginalTimeLimitInSeconds() * config('votes.time-multiplier');
            if ($secondsToAdd > $oSecondsToAdd) {
                $secondsToAdd = $oSecondsToAdd;
            }

            $totalSeconds = CountdownController::getOriginalTimeLimitInSeconds() + CountdownController::getAddedSeconds();
            $timeLimitInMinutes = config('votes.time.limit-minutes');
            if ($timeLimitInMinutes != -1) {
                if ($totalSeconds / 60 >= $timeLimitInMinutes) {
                    warningMessage('The limit of ' . secondary($timeLimitInMinutes . " min"), ' is reached.')->send($player);
                    return;
                } else if (($totalSeconds + $secondsToAdd) / 60 > $timeLimitInMinutes) {
                    warningMessage('Asking for ', secondary(($secondsToAdd / 60) . " min"), ' would exceed the limit of ' . secondary($timeLimitInMinutes . " min"))->send($player);
                    return;
                }
            }

            $voteCountLimit = config('votes.time.limit-votes');
            if ($voteCountLimit != -1 && self::$timeVotesThisRound >= $voteCountLimit) {
                warningMessage('The maximum time-vote-limit is reached, sorry.')->send($player);
                return;
            }

            if (CountdownController::getSecondsLeft() < config('votes.time.disable-in-last', 15)) {
                warningMessage('It is too late to start a vote.')->send($player);
                return;
            } else if (CountdownController::getSecondsPassed() < config('votes.time.disable-in-first', 120)) {
                warningMessage('It is too early to start a vote, please wait ', secondary((config('votes.time.disable-in-first', 120) - CountdownController::getSecondsPassed()) . ' seconds'), '.')->send($player);
                return;
            }
        }

        $verb = $secondsToAdd > 0 ? 'Add' : 'Subtract';
        $question = $verb . ' $<' . secondary(abs(round($secondsToAdd / 60, 1))) . '$> minutes?';

        $voteStarted = self::startVote($player, $question, function ($success) use ($secondsToAdd, $question) {
            if ($success) {
                self::$addTimeSuccess = true;
                successMessage('Vote ', secondary($question), ' was successful.')->sendAll();
                CountdownController::addTime($secondsToAdd);
            } else {
                self::$addTimeSuccess = false;
                dangerMessage('Vote ', secondary($question), ' did not pass.')->sendAll();
            }
        }, config('votes.time.success-ratio'));

        if ($voteStarted) {
            self::$lastTimeVote = time();
            self::$timeVotesThisRound++;

            infoMessage($player, ' started a vote to ', secondary('add ' . round($secondsToAdd / 60, 1) . ' minutes?'),
                '. Use ', secondary('F5/F6'), ' and ', secondary('/y'), ' or ', secondary('/n'),
                ' to vote.')->setIcon('')->sendAll();
        }
    }

    /**
     * @param Player $player
     * @param $cmd
     * @param string $points
     */
    public static function cmdAskMorePoints(Player $player, $cmd, $points = '0')
    {
        if (config('votes.points.enabled') === false) {
            warningMessage('Point-limit votes are disabled.')->send($player);
            return;
        }

        $points = intval($points) ?: PointsController::getOriginalPointsLimit();

        if (!$player->hasAccess('no_vote_limits')) {
            $opoints = PointsController::getOriginalPointsLimit();
            if ($points > $opoints) {
                $points = $opoints;
            }
            if (PointsController::getCurrentPointsLimit() >= config('votes.points.max-points')) {
                dangerMessage('Point limit reached.')->send($player);
                return;
            }
        }

        $question = 'Add ' . $points . ' points to limit?';

        $voteStarted = self::startVote($player, $question, function ($success) use ($points, $question) {
            if ($success) {
                successMessage('Vote ', secondary($question), ' successful, ', secondary('point-limit is now ' . (PointsController::getCurrentPointsLimit() + $points)), '.')->sendAll();
                PointsController::increasePointsLimit($points);
            } else {
                dangerMessage('Vote ', secondary($question), ' did not pass.')->sendAll();
            }
        }, config('votes.time.success-ratio'));

        if ($voteStarted) {
            infoMessage($player, ' started a vote to ', secondary($question),
                '. Use ', secondary('F5/F6'), ' and ', secondary('/y'), ' or ', secondary('/n'),
                ' to vote.')->setIcon('')->sendAll();
        }
    }

    /**
     * @param Player $player
     * @param $cmd
     * @param mixed ...$questionArray
     */
    public static function startVoteQuestion(Player $player, $cmd, ...$questionArray)
    {
        $question = implode(' ', $questionArray);

        self::startVote($player, $question, function (bool $success) use ($question) {
            infoMessage('Vote ', secondary($question), ' ended with ',
                secondary($success ? 'yes' : 'no'))->setIcon('')->sendAll();
        });
    }

    /**
     * @param Player $player
     */
    public static function askSkip(Player $player)
    {
        if (ModeController::isTimeAttackType() && (!is_null(self::$addTimeSuccess) && self::$addTimeSuccess)) {
            infoMessage('Can not skip the map after time was added.')->send($player);
            return;
        }

        $secondsPassed = time() - self::$lastSkipVote;

        if (!$player->hasAccess('no_vote_limits')) {
            if (!config('votes.skip.enabled')) {
                warningMessage('Skipping is disabled.')->send($player);
                return;
            }

            if ($secondsPassed < config('votes.skip.cooldown-in-seconds')) {
                warningMessage('Please wait ',
                    secondary((config('votes.skip.cooldown-in-seconds') - $secondsPassed) . ' seconds'),
                    ' before asking to skip the map.')->send($player);

                return;
            }

            if (self::$skipVotesThisRound >= config('votes.skip.limit-votes')) {
                warningMessage('The maximum of skip votes was reached.')->send($player);

                return;
            }

            if (ModeController::isTimeAttackType() && CountdownController::getSecondsLeft() < config('votes.skip.disable-in-last', 180)) {
                warningMessage('It is too late to skip the map.')->send($player);

                return;
            }

            if (CountdownController::getSecondsPassed() < config('votes.skip.disable-in-first', 0)) {
                warningMessage('It is too early to start a vote, please wait', secondary((config('votes.time.disable-in-first', 0) - CountdownController::getSecondsPassed()) . ' seconds'), '.')->send($player);

                return;
            }

            $diffInSeconds = self::getSecondsSinceLastSkipVote();
            if ($diffInSeconds < config('votes.skip.cooldown-in-seconds')) {
                $waitTime = config('votes.skip.cooldown-in-seconds') - $diffInSeconds;
                warningMessage('There already was a vote recently, please ', secondary('wait ' . $waitTime . ' seconds'),
                    ' before voting again.')->send($player);

                return;
            }
        }

        $voteStarted = self::startVote($player, 'Skip map?', function (bool $success) {
            if ($success) {
                successMessage('Vote to skip map was successful.')->sendAll();
                MapController::skip();
            } else {
                dangerMessage('Vote to skip map was not successful.')->sendAll();
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

    /**
     * @return Collection
     */
    private static function getVoteState(): Collection
    {
        $yesVotes = self::$voters->whereStrict('decision', 'true')->count();
        $noVotes = self::$voters->whereStrict('decision', 'false')->count();

        return collect([
            'yes' => $yesVotes,
            'no' => $noVotes,
        ]);
    }

    /**
     *
     */
    private static function updateVoteState()
    {
        $voteStateJson = self::getVoteState()->toJson();

        Template::showAll('Votes.update-vote', compact('voteStateJson'));
    }

    /**
     * @param Player $player
     */
    public static function voteYes(Player $player)
    {
        $vote = new stdClass();
        $vote->player = $player->Login;
        $vote->decision = 'true';
        self::$voters->put($player->Login, $vote);

        self::updateVoteState();
        self::checkVoteState();
    }

    /**
     * @param Player $player
     */
    public static function voteNo(Player $player)
    {
        $vote = new stdClass();
        $vote->player = $player->Login;
        $vote->decision = 'false';
        self::$voters->put($player->Login, $vote);

        self::updateVoteState();
        self::checkVoteState();
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        if (!self::$vote) {
            return;
        }

        $vote = new stdClass();
        $vote->player = $player->Login;
        $vote->decision = null;
        self::$voters->put($player->Login, $vote);
        self::$onlinePlayersCount++;
    }

    /**
     * @param Player $player
     */
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

    /**
     * @param Player $player
     */
    public static function approveVote(Player $player)
    {
        Timer::destroy('vote.check_state');
        $action = self::$vote->action;

        try {
            $action(true);
        } catch (Error $e) {
            Log::errorWithCause('Failed to approve vote', $e);
        }

        self::$vote = null;
        self::$voters = collect();
        infoMessage($player, ' passes vote.')->sendAll();
        $voteStateJson = '{"yes":-1,"no":-1}';
        Template::showAll('Votes.update-vote', compact('voteStateJson'));
    }

    /**
     * @param Player $player
     */
    public static function declineVote(Player $player)
    {
        Timer::destroy('vote.check_state');
        $action = self::$vote->action;

        try {
            $action(false);
        } catch (Error $e) {
            Log::errorWithCause('Failed to decline vote', $e);
        }

        self::$vote = null;
        self::$voters = collect();
        infoMessage($player, ' cancels vote.')->sendAll();
        $voteStateJson = '{"yes":-1,"no":-1}';
        Template::showAll('Votes.update-vote', compact('voteStateJson'));
    }

    public static function endMatch()
    {
        if (isset(self::$vote)) {
            Timer::destroy('vote.check_state');
            $action = self::$vote->action;

            try {
                $action(false);
            } catch (Error $e) {
                Log::errorWithCause('Failed to end match', $e);
            }

            $voteStateJson = '{"yes":-1,"no":-1}';
            Template::showAll('Votes.update-vote', compact('voteStateJson'));
            infoMessage('Vote cancelled.')->setIcon('')->sendAll();
        }

        self::$vote = null;
        self::$voters = collect();
    }

    public static function beginMatch()
    {
        self::$timeVotesThisRound = 0;
        self::$skipVotesThisRound = 0;
        self::$addTimeSuccess = null;
    }

    private static function getSecondsSinceLastTimeVote()
    {
        return time() - self::$lastTimeVote;
    }

    private static function getSecondsSinceLastSkipVote()
    {
        return time() - self::$lastSkipVote;
    }

    /**
     * @return null
     */
    public static function getAddTimeSuccess()
    {
        return self::$addTimeSuccess;
    }
}
