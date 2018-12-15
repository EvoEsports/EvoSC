<?php

namespace esc\Classes;

use esc\Models\Stats;
use esc\Modules\Statistics;

class StatisticWidget
{
    public $stat;
    public $title;
    public $config;
    public $prefix;
    public $suffix;

    public function __construct(string $stat, string $title, string $prefix = '', string $suffix = '', $function = null, $sortAsc = false)
    {
        $this->stat   = $stat;
        $this->title  = $title;
        $this->config = config('statistics.' . $stat);

        if ($sortAsc) {
            $this->records = Stats::orderBy($stat)->get();
        }else{
            $this->records = Stats::orderByDesc($stat)->get();
        }

        //Get records as nickname => value
        $this->records = $this->records->take($this->config->show)->pluck($stat, 'player');

        //Get rid of zero value records
        $this->records = $this->records->filter(function ($value) {
            return $value > 0;
        });

        if ($function) {
            //Execute function on values
            $this->records = $this->records->map($function);
        }

        $this->prefix = $prefix;
        $this->suffix = $suffix;
    }
}