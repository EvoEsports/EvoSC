<?php


namespace EvoSC\Modules\Classes;


use Illuminate\Support\Collection;

class MxKarmaMapRating
{
    private int $total_votes;
    private float $vote_avg;
    private Collection $votes;

    public function __construct(\stdClass $mxKarmaResult)
    {
        $this->total_votes = $mxKarmaResult->votecount;
        $this->vote_avg = $mxKarmaResult->voteaverage;
        $this->votes = collect($mxKarmaResult->votes)->pluck('vote', 'login');
    }

    /**
     * @return int
     */
    public function getTotalVotes(): int
    {
        return $this->total_votes;
    }

    /**
     * @return float
     */
    public function getVoteAvg(): float
    {
        return $this->vote_avg;
    }

    /**
     * @return Collection
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }
}