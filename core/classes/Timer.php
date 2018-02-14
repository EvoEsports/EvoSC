<?php

namespace esc\classes;


class Timer
{
    private static $interval = 100; //one tick each X milliseconds
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

    /**
     * Converts time string to minutes
     * Example: 3h -> 180, 1d -> 1440, 1mo12h9m -> 43321
     * m = minutes, h = hours, d = days, w = weeks, mo = months
     * @param string $durationShort
     * @return int
     */
    public static function textTimeToMinutes(string $durationShort): int
    {
        if (preg_match('/^\d+$/', $durationShort)) {
            return intval($durationShort);
        }

        $time = 0;

        if (preg_match('/(\d)m/', $durationShort, $matches)) {
            $time += intval($matches[1]);
        }
        if (preg_match('/(\d)h/', $durationShort, $matches)) {
            $time += intval($matches[1]) * 60;
        }
        if (preg_match('/(\d)d/', $durationShort, $matches)) {
            $time += intval($matches[1]) * 60 * 24;
        }
        if (preg_match('/(\d)w/', $durationShort, $matches)) {
            $time += intval($matches[1]) * 60 * 24 * 7;
        }
        if (preg_match('/(\d)mo/', $durationShort, $matches)) {
            $time += intval($matches[1]) * 60 * 24 * 30;
        }

        return $time;
    }

    public static function formatScore(int $score): string
    {
        $seconds = floor($score / 1000);
        $ms = $score - ($seconds * 1000);
        $minutes = floor($seconds / 60);
        $seconds -= $minutes * 60;

        return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
    }
}