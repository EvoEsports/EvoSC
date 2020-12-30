<?php


namespace EvoSC\Controllers;


use Carbon\Carbon;
use EvoSC\Classes\Cache;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Controller;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use SimpleXMLElement;

class CountdownController extends Controller implements ControllerInterface
{
    /**
     * @var int
     */
    private static int $addedSeconds = 0;

    /**
     * @var int
     */
    private static int $matchStart = -1;

    /**
     * @var int
     */
    private static int $originalTimeLimit = -1;

    public static function init()
    {
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        if(ModeController::isRoundsType()){
            return;
        }

        self::$originalTimeLimit = ModeController::isRoundsType() ? 0 : self::getTimeLimitFromMatchSettings();

        if (Cache::has('match-start')) {
            self::$matchStart = Cache::get('match-start');
        }
        if (Cache::has('added-time')) {
            self::$addedSeconds = Cache::get('added-time');
        }

        Hook::add('BeginMatch', [self::class, 'beginMatch']);
        Hook::add('EndMap', [self::class, 'endMap']);
        Hook::add('MatchSettingsLoaded', [self::class, 'matchSettingsLoaded']);

        ChatCommand::add('//addtime', [self::class, 'addTimeManually'],
            'Add time in minutes to the countdown (you can add negative time or decimals like 0.5 for 30s)', 'manipulate_time');
        ChatCommand::add('/hunt', [self::class, 'enableHuntMode'], 'Enable hunt mode (disable countdown).', 'manipulate_time');

        InputSetup::add('add_one_minute', 'Add one minute to the countdown.', [self::class, 'addMinute'], 'F9', 'manipulate_time');
    }

    public static function stop()
    {
        Cache::put('added-time', self::$addedSeconds, now()->addMinute());
        Cache::put('match-start', self::$matchStart, now()->addMinute());
    }

    public static function beginMatch()
    {
        self::$matchStart = time();
        self::$addedSeconds = 0;
        Cache::put('added-time', 0);
        Cache::put('match-start', self::$matchStart);
        self::setTimeLimit(self::getOriginalTimeLimitInSeconds());
    }

    public static function endMap()
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
     * @param int $seconds
     * @param Player|null $player
     */
    public static function addTime(int $seconds, Player $player = null)
    {
        $addedTime = self::$addedSeconds;
        $addedTime += $seconds;

        Hook::fire('AddedTimeChanged', $addedTime);
        Cache::put('added-time', $addedTime);

        self::$addedSeconds = $addedTime;
        self::setTimeLimit(self::getOriginalTimeLimitInSeconds() + $addedTime);

        if ($player != null) {
            if ($seconds > 0) {
                infoMessage($player, ' added ', secondary(round($seconds / 60, 1) . ' minutes'),
                    ' of playtime.')->sendAdmin();
            } else {

                infoMessage($player, ' removed ', secondary(round($seconds / -60, 1) . ' minutes'),
                    ' of playtime.')->sendAdmin();
            }
        }
    }

    /**
     * @param Player $player
     * @param string $cmd
     * @param string $amount
     */
    public static function addTimeManually(Player $player, string $cmd, string $amount)
    {
        self::addTime(round(floatval($amount) * 60), $player);
    }

    /**
     * @param Player $player
     */
    public static function addMinute(Player $player)
    {
        self::addTime(60, $player);
    }

    /**
     * @param bool $getAsCarbon
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
    public static function getSecondsLeft(): ?int
    {
        $roundStart = self::getRoundStartTime();

        if (!$roundStart) {
            return null;
        }

        $calculatedProgressTime = self::getRoundStartTime() + self::getOriginalTimeLimitInSeconds() + self::$addedSeconds;

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
     * @param string|null $file
     * @return int
     */
    private static function getTimeLimitFromMatchSettings(string $file = null): int
    {
        if (!$file) {
            $file = MatchSettingsController::getCurrentMatchSettingsFile();
        }

        if ($file) {
            $matchSettings = File::get(MapController::getMapsPath('MatchSettings/' . $file));
            $xml = new SimpleXMLElement($matchSettings);
            $node = null;

            if (isset($xml->mode_script_settings)) {
                $node = $xml->mode_script_settings;
            } else {
                $node = $xml->script_settings;
            }

            if ($node) {
                foreach ($node->children() as $child) {
                    if ($child->attributes()['name'] == 'S_TimeLimit') {
                        return intval($child->attributes()['value']);
                    }
                }
            }
        }

        Log::warning("Time limit not set in match-settings, using default.");

        return 600;
    }

    public static function matchSettingsLoaded(string $matchSettingsFile)
    {
        self::$originalTimeLimit = self::getTimeLimitFromMatchSettings($matchSettingsFile);
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
    public static function getOriginalTimeLimitInSeconds()
    {
        return self::$originalTimeLimit;
    }

    /**
     * Set a new time limit in seconds.
     *
     * @param int $seconds
     */
    public static function setTimeLimit(int $seconds)
    {
        $settings = Server::getModeScriptSettings();
        $settings['S_TimeLimit'] = $seconds;
        Server::setModeScriptSettings($settings);
    }
}