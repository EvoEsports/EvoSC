<?php


namespace EvoSC\Modules\CPRecords\Classes;


class CPRecordsTracker
{
    public $cpId;
    public $name;
    public $time;
    public $isFinish;

    public function __construct($cpId, $name, $time, $isFinish)
    {
        $this->cpId = $cpId;
        $this->name = $name;
        $this->time = $time;
        $this->isFinish = $isFinish;
    }
}