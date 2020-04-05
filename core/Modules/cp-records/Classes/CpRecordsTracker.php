<?php


namespace esc\Modules\Classes;


class CpRecordsTracker
{
    public $cpId;
    public $name;
    public $time;

    public function __construct($cpId, $name, $time)
    {
        $this->cpId = $cpId;
        $this->name = $name;
        $this->time = $time;
    }
}