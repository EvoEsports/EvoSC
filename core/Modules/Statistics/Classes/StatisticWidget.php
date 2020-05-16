<?php

namespace EvoSC\Modules\Statistics\Classes;

use EvoSC\Classes\DB;

class StatisticWidget
{
    public string $stat;
    public string $title;
    public $pos;
    public $scale;
    public $show;
    public string $prefix;
    public string $suffix;
    public bool $nameLeft;

    public function __construct(
        string $stat,
        string $title,
        string $prefix = '',
        string $suffix = '',
        $function = null,
        $sortAsc = false,
        $nameLeft = true,
        $collection = null
    ) {
        $this->stat = $stat;
        $this->title = $title;
        $this->pos = config('statistics.'.$stat.'.pos');
        $this->show = config('statistics.'.$stat.'.show');
        $this->scale = config('statistics.'.$stat.'.scale');

        if (!$collection) {
            $queryBuilder = DB::table('stats')
                ->join('players', 'players.id', '=', 'stats.Player')
                ->where($stat, '>', 0)
                ->select(['NickName', $stat]);

            if ($sortAsc) {
                $this->records = $queryBuilder->orderBy($stat)->take($this->show)->get();
            } else {
                $this->records = $queryBuilder->orderByDesc($stat)->take($this->show)->get();
            }

            //Get records as nickname => value
            $this->records = $this->records->pluck($stat, 'NickName');
        } else {
            $this->records = $collection;
        }

        if ($function) {
            //Execute function on values
            $this->records = $this->records->transform($function);
        }

        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->nameLeft = $nameLeft;
    }
}