<?php

namespace esc\Classes;


use Carbon\Carbon;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;

class Vote
{
    const VOTE_TIME = 15;

    private static $inProgress;
    private static $message;
    private static $votes;
    private static $action;
    private static $startTime;
    private static $starter;
    private static $lastVote;

    public $player;
    public $decision;

    public function __construct(Player $player, bool $decision)
    {
        $this->player = $player;
        $this->decision = $decision;
    }

    public static function init()
    {
        self::$inProgress = false;

        Template::add('vote', File::get('core/Templates/vote.latte.xml'));

        Hook::add('EndMatch', 'esc\Classes\Vote::endMatch');

        ChatController::addCommand('replay', 'esc\classes\Vote::replayMap', 'Cast a vote to replay map');
        ChatController::addCommand('res', 'esc\classes\Vote::replayMap',
            'Cast a vote to replay map (Alias for /replay)');
        ChatController::addCommand('skip', 'esc\classes\Vote::skipMap', 'Cast a vote to skip map');
        ChatController::addCommand('y', 'esc\classes\Vote::voteYes', 'Vote yes');
        ChatController::addCommand('n', 'esc\classes\Vote::voteNo', 'Vote no');
    }

    public static function active(): bool
    {
        return self::$active ?: false;
    }

    public static function voteYes(Player $player)
    {
        if (!self::$inProgress) {
            ChatController::message($player, 'There is no vote in progress');

            return;
        }

        self::vote($player, true);
    }

    public static function voteNo(Player $player)
    {
        if (!self::$inProgress) {
            ChatController::message($player, 'There is no vote in progress');

            return;
        }

        self::vote($player, false);
    }

    public static function endMatch()
    {
        if (self::$inProgress) {
            self::stopVote();
        }
    }

    private static function vote(Player $player, bool $decision)
    {
        $alreadyVoted = self::$votes->where('player.Login', $player->Login);

        if ($alreadyVoted->isEmpty()) {
            self::$votes->push(new Vote($player, $decision));
        } else {
            $alreadyVoted->first()->decision = $decision;
        }

        $nonSpectators = Player::whereOnline(true)
            ->whereSpectator(false)
            ->get();

        if (count(self::$votes) == $nonSpectators->count()) {
            self::finishVote();
        }

        self::showVote();
    }

    public static function replayMap(Player $player)
    {
        if (self::$inProgress) {
            ChatController::message($player, 'There is already a vote in progress');

            return;
        }

        if (self::$lastVote != null && self::$lastVote->diffInSeconds() < 180) {
            $waitTimeInSeconds = 180 - self::$lastVote->diffInSeconds();

            ChatController::message($player, 'Please wait ', secondary($waitTimeInSeconds . ' seconds'),
                ' before voting again.');

            return;
        }

        self::$lastVote = Carbon::now();
        self::$votes = new Collection();
        self::$inProgress = true;
        self::$message = 'Play for another 5 minutes?';
        self::$startTime = time();
        self::$action = 'esc\classes\Vote::doReplay';
        self::$starter = $player;

        self::voteYes($player);

        Timer::create('vote.finish', 'esc\classes\Vote::finishVote', self::VOTE_TIME . 's');

        ChatController::messageAll($player, ' is asking for more time. Type /y or /n to vote.');

        self::showVote();
    }

    public static function skipMap(Player $player)
    {
        if (self::$inProgress) {
            ChatController::message($player, 'There is already a vote in progress');

            return;
        }

        if (self::$lastVote != null && self::$lastVote->diffInSeconds() < 180) {
            $waitTimeInSeconds = 180 - self::$lastVote->diffInSeconds();

            ChatController::message($player, 'Please wait ', secondary($waitTimeInSeconds . ' seconds'),
                ' before voting again.');

            return;
        }

        self::$lastVote = Carbon::now();
        self::$votes = new Collection();
        self::$inProgress = true;
        self::$message = 'Skip map?';
        self::$startTime = time();
        self::$action = 'esc\classes\Vote::doSkip';
        self::$starter = $player;

        self::voteYes($player);

        Timer::create('vote.finish', 'esc\classes\Vote::finishVote', self::VOTE_TIME . 's');

        ChatController::messageAll($player, ' is asking to skip the map. Type /y or /n to vote.');

        self::showVote();
    }

    public static function doReplay()
    {
        MapController::addTime();
    }

    public static function doSkip()
    {
        MapController::skip(Player::console());
    }

    public static function finishVote()
    {
        $yesVotes = self::$votes->where('decision', true)
            ->count();

        $noVotes = self::$votes->where('decision', false)
            ->count();

        $successful = $yesVotes > $noVotes;

        if ($successful) {
            call_user_func(self::$action);
        }

        $voteText = '$' . config('color.secondary') . self::$message;

        ChatController::messageAll('Vote ', $voteText, ' was ', $successful ? 'successful' : 'not successful');

        self::stopVote();
    }

    public static function showVote()
    {
        if (self::$inProgress) {
            $yesVotes = self::$votes->where('decision', true)
                ->count();
            $noVotes = self::$votes->where('decision', false)
                ->count();

            $totalVotes = $yesVotes + $noVotes;

            if ($totalVotes > 0) {
                $yesRatio = ($yesVotes * 100) / $totalVotes;
                $noRatio = ($noVotes * 100) / $totalVotes;
            }

            $yes = ($yesRatio ?: 0) * 0.35;
            $no = ($noRatio ?: 0) * 0.35;

            $timeleft = (self::$startTime + self::VOTE_TIME) - time();

            Template::showAll('vote', [
                'message'      => self::$message,
                'yes'          => $yes,
                'no'           => $no,
                'yesN'         => $yesVotes,
                'noN'          => $noVotes,
                'voteDuration' => self::VOTE_TIME,
                'timeLeft'     => $timeleft,
            ]);
        }
    }

    public static function hideVote()
    {
        Template::hideAll('Vote');
    }

    public static function stopVote(Player $player = null)
    {
        if (!self::$inProgress) {
            ChatController::message($player, 'There is currently no vote to stop');

            return;
        }

        if ($player) {
            ChatController::messageAll($player, ' stops vote');
        }

        Timer::stop('vote.finish');

        self::$inProgress = false;
        self::$starter = null;
        self::$message = null;
        self::$action = null;
        self::$votes = collect([]);
        self::hideVote();
    }

    public static function approveVote(Player $player)
    {
        if (!self::$inProgress) {
            ChatController::message($player, 'There is currently no vote to approve');

            return;
        }

        ChatController::messageAll($player, ' approves vote');
        call_user_func(self::$action);
        self::stopVote();
    }
}