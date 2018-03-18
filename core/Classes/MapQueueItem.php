<?php

namespace esc\Classes;

use esc\Models\Map;
use esc\Models\Player;

class MapQueueItem
{
    public $issuer;
    public $map;
    public $timeRequested;

    public function __construct(Player $issuer, Map $map, int $timeRequested)
    {
        $this->issuer = $issuer;
        $this->map = $map;
        $this->timeRequested = $timeRequested;
    }
}