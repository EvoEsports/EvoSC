<?php

namespace esc\Classes;


use Illuminate\Support\Collection;

class Timer
{
    private static $interval = 100; //one tick each X milliseconds
    private static $uStart;
    private static $timers;

    public $id;
    public $callback;
    public $runtime;
    public $repeat;
    public $delay;

    private function __construct(string $id, $callback, int $runtime, bool $repeat = false)
    {
        $this->id       = $id;
        $this->callback = $callback;
        $this->runtime  = $runtime;
        $this->repeat   = $repeat;
    }

    public function setNewRuntimeDelay($delay)
    {
        $this->runtime = time() + self::textTimeToSeconds($delay);
    }

    /**
     * Gets timer with id
     *
     * @param string $id
     *
     * @return Timer|null
     */
    public static function getTimer(string $id): ?Timer
    {
        $timer = self::$timers->where('id', $id)->first();

        if (!$timer) {
            Log::warning("Can not get non-existent timer: $id");

            return null;
        }

        return $timer;
    }

    /**
     * Creates a new timer
     *
     * @param string                $id
     * @param string|array|callable $callback
     * @param string                $delayTime
     * @param bool                  $repeat
     */
    public static function create(string $id, $callback, string $delayTime, bool $repeat = false)
    {
        if (!self::$timers) {
            self::$timers = new Collection();
        }

        $timers = self::$timers;

        if ($timers->where('id', $id)->isNotEmpty()) {
            Log::warning("Timer with id: $id already exists, not setting.");

            return;
        }

        $runtime = time() + self::textTimeToSeconds($delayTime);

        $timer        = new Timer($id, $callback, $runtime, $repeat);
        $timer->delay = $delayTime;

        $timers->push($timer);
    }

    public static function destroy(string $string)
    {
        self::$timers = self::$timers->where('id', '!=', $string);
    }

    /**
     * Delays a timer
     *
     * @param string $id
     * @param string $timeString
     */
    public static function addDelay(string $id, string $timeString)
    {
        $timer = self::getTimer($id);

        if ($timer) {
            $timer->runtime += self::textTimeToSeconds($timeString);
        }
    }

    /**
     * Gets seconds left until the timer is executed
     *
     * @param string $id
     *
     * @return int
     */
    public static function secondsLeft(string $id): int
    {
        $timer = self::getTimer($id);

        if ($timer) {
            return time() - $timer->runtime;
        }

        return -1;
    }

    /**
     * Executes timers
     */
    private static function executeTimers()
    {
        if (!self::$timers) {
            return;
        }

        $toRun        = self::$timers->where('runtime', '<', time());
        $toRemove     = $toRun->where('repeat', false);
        self::$timers = self::$timers->diff($toRemove);

        foreach ($toRun as $timer) {
            if (gettype($timer->callback) == "object") {
                $func = $timer->callback;
                $func();
            } else {
                call_user_func($timer->callback);
            }

            if ($timer->repeat) {
                $timer->setNewRuntimeDelay($timer->delay);
            }
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
     *
     * @return int
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
     *
     * @param string $durationShort
     *
     * @return int
     */
    public static function textTimeToMinutes(string $durationShort): int
    {
        $time = 0;

        if (preg_match('/(\d+)m/', $durationShort, $matches)) {
            $time += intval($matches[1]);
        }
        if (preg_match('/(\d+)h/', $durationShort, $matches)) {
            $time += intval($matches[1]) * 60;
        }
        if (preg_match('/(\d+)d/', $durationShort, $matches)) {
            $time += intval($matches[1]) * 60 * 24;
        }
        if (preg_match('/(\d+)w/', $durationShort, $matches)) {
            $time += intval($matches[1]) * 60 * 24 * 7;
        }
        if (preg_match('/(\d+)mo/', $durationShort, $matches)) {
            $time += intval($matches[1]) * 60 * 24 * 30;
        }

        return $time;
    }

    /**
     * Converts time string to seconds
     * m = minutes, h = hours, d = days, w = weeks, mo = months
     *
     * @param string $durationShort
     *
     * @return int
     */
    public static function textTimeToSeconds(string $durationShort): int
    {
        $seconds = self::textTimeToMinutes($durationShort) * 60;

        if (preg_match('/(\d+)s/', $durationShort, $matches)) {
            $seconds += intval($matches[1]);
        }

        return $seconds;
    }

    public static function scoreToReadableTime(int $score): string
    {
        return formatScore($score);
    }

    public static function stop(string $id)
    {
        self::$timers = self::$timers->diff(self::$timers->where('id', $id));
    }

    /**
     * @param int $interval
     */
    public static function setInterval(int $interval): void
    {
        self::$interval = $interval;
    }

    /**
     * Creates a hash from the timer
     *
     * @return string
     */
    public function __toString()
    {
        return "timer:$this->id";
    }
}