<?php

namespace esc\Classes;

use esc\Models\Stats;

class StatisticWidget
{
    public $stat;
    public $title;
    public $pos;
    public $scale;
    public $show;
    public $prefix;
    public $suffix;
    public $nameLeft;

    public function __construct(string $stat, string $title, string $prefix = '', string $suffix = '', $function = null, $sortAsc = false, $nameLeft = true, $collection = null)
    {
        $this->stat  = $stat;
        $this->title = $title;
        $this->pos   = config('statistics.' . $stat . '.pos');
        $this->show  = config('statistics.' . $stat . '.show');
        $this->scale = config('statistics.' . $stat . '.scale');

        if (!$collection) {
            if ($sortAsc) {
                $this->records = Stats::orderBy($stat)->where($stat, '>', 0)->limit($this->show)->get();
            } else {
                $this->records = Stats::orderByDesc($stat)->where($stat, '>', 0)->limit($this->show)->get();
            }

            //Get records as nickname => value
            $this->records = $this->records->pluck($stat, 'player');
        } else {
            $this->records = $collection;
        }

        if ($function) {
            //Execute function on values
            $this->records = $this->records->transform($function);
        }

        $this->prefix   = $prefix;
        $this->suffix   = $suffix;
        $this->nameLeft = $nameLeft;
    }
}