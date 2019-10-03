<?php


namespace esc\Controllers;


use Carbon\Carbon;
use esc\Classes\Cache;
use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Timer;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\Player;
use esc\Modules\KeyBinds;
use Exception;
use SimpleXMLElement;

class CountdownController implements ControllerInterface
{
    /**
     * @var int
     */
    private static $addedSeconds = 0;

    /**
     * @var int
     */
    private static $matchStart;

    /**
     * @var int
     */
    private static $originalTimeLimit;

    /**
     *
     */
    public static function init()
    {
        if (Cache::has('added-time')) {
            self::$addedSeconds = Cache::get('added-time');
        }
        if (Cache::has('match-start')) {
            self::$matchStart = Cache::get('match-start');
        } else {
            self::$matchStart = time();
        }

        AccessRight::createIfMissing('hunt', 'Enabled/disable hunt mode.');
    }

    /**
     *
     */
    public static function setMatchStart()
    {
        self::$matchStart = time();
        self::$addedSeconds = 0;
        Cache::put('match-start', self::$matchStart);
    }

    public static function endMap(Map $map)
    {
        self::resetTimeLimit();
    }

    public static function resetTimeLimit()
    {
        self::setTimeLimit(self::$originalTimeLimit);
        Cache::put('added-time', 0);
    }

    public static function enableHuntMode(Player $player)
    {
        self::setTimeLimit(0);
        infoMessage($player, ' enabled hunt mode.')->sendAll();
    }

    /**
     * @param  int  $seconds
     * @param  Player|null  $player
     */
    public static function addTime(int $seconds, Player $player = null)
    {
        $addedTime = self::$addedSeconds;
        $addedTime += $seconds;

        Hook::fire('AddedTimeChanged', $addedTime);

        self::$addedSeconds = $addedTime;
        self::setTimeLimit(self::getOriginalTimeLimit() + $addedTime);

        if ($player != null) {
            if ($seconds > 0) {
                infoMessage($player, ' added ', secondary(round($seconds / 60, 1).' minutes'),
                    ' of playtime.')->sendAdmin();
            } else {

                infoMessage($player, ' removed ', secondary(round($seconds / -60, 1).' minutes'),
                    ' of playtime.')->sendAdmin();
            }
        }

        Cache::put('added-time', $addedTime);
    }

    /**
     * @param  Player  $player
     * @param  string  $cmd
     * @param  float  $amount
     */
    public static function addTimeManually(Player $player, $cmd, float $amount)
    {
        self::addTime(round($amount * 60), $player);
    }

    /**
     * @param  Player  $player
     */
    public static function addMinute(Player $player)
    {
        self::addTime(60, $player);
    }

    /**
     * @param  bool  $getAsCarbon
     *
     * @return Carbon|int
     */
    public static function getRoundStartTime(bool $getAsCarbon = false)
    {
        if ($getAsCarbon) {
            return Carbon::createFromTimestamp(self::$matchStart);
        } else {
            return self::$matchStart;
        }
    }

    /**
     * @return int
     */
    public static function getSecondsLeft(): int
    {
        $calculatedProgressTime = self::getRoundStartTime() + self::getOriginalTimeLimit() + self::$addedSeconds + 3;

        $timeLeft = $calculatedProgressTime - time();

        return $timeLeft < 0 ? 0 : $timeLeft;
    }

    /**
     * @return int
     */
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
            $matchSettings = File::get(MapController::getMapsPath('MatchSettings/'.$file));
            $xml = new SimpleXMLElement($matchSettings);
            foreach ($xml->mode_script_settings->children() as $child) {
                if ($child->attributes()['name'] == 'S_TimeLimit') {
                    return intval($child->attributes()['value']);
                }
            }
        }

        return 600;
    }

    public static function beginMap(Map $map)
    {
    }

    /**
     * @return integer
     */
    public static function getAddedSeconds()
    {
        return self::$addedSeconds;
    }

    /**
     * @return integer
     */
    public static function getOriginalTimeLimit()
    {
        return self::$originalTimeLimit;
    }

    /**
     * Set a new time limit in seconds.
     *
     * @param  int  $seconds
     */
    public static function setTimeLimit(int $seconds)
    {
        $settings = Server::getModeScriptSettings();
        $settings['S_TimeLimit'] = $seconds;
        Server::setModeScriptSettings($settings);
    }

    /**
     * @param  string  $mode
     * @param  bool  $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        self::$originalTimeLimit = self::getTimeLimitFromMatchSettings();

        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('BeginMatch', [self::class, 'setMatchStart']);
        Hook::add('EndMap', [self::class, 'endMap']);

        ChatCommand::add('//addtime', [self::class, 'addTimeManually'],
            'Add time in minutes to the countdown (you can add negative time or decimals like 0.5 for 30s)', 'time');
        ChatCommand::add('/hunt', [self::class, 'enableHuntMode'], 'Enable hunt mode (disable countdown).', 'hunt');

        KeyBinds::add('add_one_minute', 'Add one minute to the countdown.', [self::class, 'addMinute'], 'F9', 'time');
    }
}