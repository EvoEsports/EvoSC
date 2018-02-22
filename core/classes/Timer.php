<?php

namespace esc\classes;


use Illuminate\Support\Collection;

class Timer
{
    private static $interval = 500; //one tick each X milliseconds
    private static $uStart;

    private static $timers;

    /**
     * Creates a new timer
     * @param string $id
     * @param string $callback
     * @param int $delayInSeconds
     * @param bool $override
     */
    public static function create(string $id, string $callback, string $delayTime, bool $override = false)
    {
        if (!self::$timers) self::$timers = new Collection();

        $timers = self::$timers;

        if ($timers->where('id', $id)->isNotEmpty() && !$override) {
            Log::warning("Timer with id: $id already exists, not setting.");
            return;
        }

        $runtime = time() + self::textTimeToSeconds($delayTime);

        $timer = collect([
            'id' => $id,
            'callback' => $callback,
            'runtime' => $runtime
        ]);

        $timers->push($timer);
    }

    /**
     * Executes timers
     */
    private static function executeTimers()
    {
        $toRun = self::$timers->where('runtime', '<', time());
        self::$timers = self::$timers->diff($toRun);

        foreach ($toRun as $timer) {
            call_user_func($timer['callback']);
        }
    }

    /**
     * Start a new cycle
     */
    public static function startCycle()
    {
        self::$uStart = microtime(true);
    }

    /**
     * Calculate the sleep time
     * @return float|int
     */
    public static function getNextCyclePause(): int
    {
        self::executeTimers();

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

    /**
     * Converts time string to seconds
     * m = minutes, h = hours, d = days, w = weeks, mo = months
     * @param string $durationShort
     * @return int
     */
    public static function textTimeToSeconds(string $durationShort): int
    {
        $seconds = self::textTimeToMinutes($durationShort) * 60;

        if (preg_match('/(\d)s/', $durationShort, $matches)) {
            $seconds += intval($matches[1]);
        }

        return $seconds;
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