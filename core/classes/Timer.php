<?php

namespace esc\classes;


class Timer
{
    private static $interval = 1000; //one tick each X milliseconds
    private static $uStart;

    public static function startCycle()
    {
        self::$uStart = microtime(true);
    }

    public static function getNextCyclePause()
    {
        $nextCyclePause = (self::$interval / 1000) + self::$uStart - microtime(true);

        if ($nextCyclePause < 0) {
            return 0;
        }

        return round($nextCyclePause * 1000000);
    }
}