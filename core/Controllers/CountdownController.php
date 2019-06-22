<?php


namespace esc\Controllers;


use Carbon\Carbon;
use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Models\Player;
use esc\Modules\KeyBinds;

class CountdownController
{
    private static $addedSeconds = 0;
    private static $matchStart;
    private static $originalTimeLimit;

    public static function init()
    {
        self::$originalTimeLimit = self::getTimeLimitFromMatchSettings();

        if (File::exists(cacheDir('added_time.txt'))) {
            self::$addedSeconds = intval(File::get(cacheDir('added_time.txt')));
        }
        if (File::exists(cacheDir('round_start_time.txt'))) {
            self::$matchStart = intval(File::get(cacheDir('round_start_time.txt')));
        }

        Hook::add('BeginMatch', [self::class, 'setMatchStart']);
        Hook::add('EndMatch', [self::class, 'setMatchStart']);

        ChatCommand::add('//addtime', [self::class, 'addTimeManually'],
            'Add time in minutes to the countdown (you can add negative time or decimals like 0.5 for 30s)', 'time');

        KeyBinds::add('add_one_minute', 'Add one minute to the countdown.', [self::class, 'addMinute'], 'F3', 'time');
    }

    public static function setMatchStart()
    {
        $time               = time();
        self::$matchStart   = $time;
        self::$addedSeconds = 0;

        self::setTimeLimit(self::getOriginalTimeLimit());

        try {
            $file = cacheDir('round_start_time.txt');
            File::put($file, $time);
        } catch (\Exception $e) {
            Log::logAddLine('CountdownController', 'Failed to save match start to cache-file.');
        }
    }

    public static function addTime(int $seconds, Player $player = null)
    {
        $addedTime = self::$addedSeconds;
        $addedTime += $seconds;

        Hook::fire('AddedTimeChanged', $addedTime);

        self::$addedSeconds = $addedTime;
        self::setTimeLimit(self::getOriginalTimeLimit() + $addedTime);

        if ($player != null) {
            if ($seconds > 0) {
                infoMessage($player, ' added ', secondary(round($seconds / 60, 1) . ' minutes'), ' of playtime.')->sendAdmin();
            } else {

                infoMessage($player, ' removed ', secondary(round($seconds / -60, 1) . ' minutes'), ' of playtime.')->sendAdmin();
            }
        }

        try {
            $file = cacheDir('added_time.txt');
            File::put($file, $addedTime);
        } catch (\Exception $e) {
            Log::logAddLine('CountdownController', 'Failed to save added time to cache-file.');
        }
    }

    public static function addTimeManually(Player $player, $cmd, float $amount)
    {
        self::addTime(round($amount * 60), $player);
    }

    public static function addMinute(Player $player)
    {
        self::addTime(60, $player);
    }

    /**
     * @param bool $getAsCarbon
     *
     * @return \Carbon\Carbon|int
     */
    public static function getRoundStartTime(bool $getAsCarbon = false)
    {
        if ($getAsCarbon) {
            return Carbon::createFromTimestamp(self::$matchStart);
        } else {
            return self::$matchStart;
        }
    }

    public static function getSecondsLeft(): int
    {
        $calculatedProgressTime = self::getRoundStartTime() + self::getOriginalTimeLimit() + self::$addedSeconds + 2;

        $timeLeft = $calculatedProgressTime - time();

        return $timeLeft < 0 ? 0 : $timeLeft;
    }

    public static function getSecondsPassed(): int
    {
        $startTime = self::getRoundStartTime();

        return time() - $startTime;
    }

    /**
     * Load the time limit from the default match-settings.
     *
     * @return int
     */
    private static function getTimeLimitFromMatchSettings(): int
    {
        $file = config('server.default-matchsettings');

        if ($file) {
            $matchSettings = File::get(MapController::getMapsPath('MatchSettings/' . $file));
            $xml           = new \SimpleXMLElement($matchSettings);
            foreach ($xml->mode_script_settings->children() as $child) {
                if ($child->attributes()['name'] == 'S_TimeLimit') {
                    return intval($child->attributes()['value']);
                }
            }
        }

        return 600;
    }

    /**
     * @return mixed
     */
    public static function getAddedSeconds()
    {
        return self::$addedSeconds;
    }

    /**
     * @return mixed
     */
    public static function getOriginalTimeLimit()
    {
        return self::$originalTimeLimit;
    }

    /**
     * Set a new timelimit in seconds.
     *
     * @param int $seconds
     */
    public static function setTimeLimit(int $seconds)
    {
        $settings                = Server::getModeScriptSettings();
        $settings['S_TimeLimit'] = $seconds;
        Server::setModeScriptSettings($settings);
    }
}