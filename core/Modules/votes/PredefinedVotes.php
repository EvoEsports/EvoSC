<?php

namespace esc\Classes;


use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Player;
use esc\Modules\Votes;

class PredefinedVotes
{
    protected static $addTimeFailed = false;

    /**
     * Start a vote for more time
     *
     * @param \esc\Models\Player $player
     */
    public static function askMoreTime(Player $player)
    {
        if (Votes::voteInProgress()) {
            ChatController::message($player, '_warning', 'There is already a vote in progress.');

            return;
        }

        $vote = new Vote("Add 10 minutes playtime?", [MapController::class, 'addTime']);

        Votes::showWidget($vote);
        Votes::setVote($vote);
    }

    /**
     * Start a vote to skip the current map
     *
     * @param \esc\Models\Player $player
     */
    public static function askSkip(Player $player)
    {
        if (Votes::voteInProgress()) {
            ChatController::message($player, '_warning', 'There is already a vote in progress.');

            return;
        }

        $vote = new Vote("Skip map?", [MapController::class, 'skip'], $player);

        Votes::showWidget($vote);
        Votes::setVote($vote);
    }
}