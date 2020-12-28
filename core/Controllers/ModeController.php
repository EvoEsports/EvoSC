<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Classes\Timer;
use EvoSC\Commands\EscRun;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\QuickButtons\QuickButtons;

class ModeController implements ControllerInterface
{
    private static bool $isTimeAttackType;
    private static bool $isRoundsType;
    private static bool $laps;
    private static bool $teams;
    private static bool $cup;

    /**
     *
     */
    public static function init()
    {
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        self::setMode($mode);
    }

    /**
     * @param string $mode
     */
    public static function setMode(string $mode)
    {
        self::$teams = false;
        self::$laps = false;
        self::$cup = false;
        self::$isTimeAttackType = false;
        self::$isRoundsType = false;

        switch ($mode) {
            case 'TimeAttack.Script.txt':
            case 'Trackmania/TM_TimeAttack_Online.Script.txt':
                self::$isTimeAttackType = true;
                self::$isRoundsType = false;
                break;

            case 'Rounds.Script.txt':
            case 'Trackmania/TM_Rounds_Online.Script.txt':
                self::$isTimeAttackType = false;
                self::$isRoundsType = true;
                break;

            case 'Laps.Script.txt':
            case 'Trackmania/TM_Laps_Online.Script.Script.txt':
                self::$isTimeAttackType = false;
                self::$isRoundsType = true;
                self::$laps = true;
                break;

            case 'Teams.Script.txt':
            case 'Trackmania/TM_Teams_Online.Script.txt':
                self::$isTimeAttackType = false;
                self::$isRoundsType = true;
                self::$teams = true;
                break;

            case 'Cup.Script.txt':
            case 'Trackmania/TM_Cup_Online.Script.Script.txt':
                self::$isTimeAttackType = false;
                self::$isRoundsType = true;
                self::$cup = true;
                break;
        }
    }

    public static function rebootModules()
    {
        $mode = Server::getScriptName()['NextValue'];

        HookController::init();
        ChatCommand::removeAll();
        Timer::destroyAll();
        ManiaLinkEvent::removeAll();
        InputSetup::clearAll();
        if (config('quick-buttons.enabled')) {
            QuickButtons::removeAll();
        }

        ControllerController::loadControllers($mode);
        self::setMode($mode);
        ModuleController::startModules($mode, false);
        EscRun::addBootCommands();
    }

    /**
     * @return bool
     */
    public static function isRoundsType(): bool
    {
        return self::$isRoundsType;
    }

    /**
     * @return bool
     */
    public static function isTimeAttackType(): bool
    {
        return self::$isTimeAttackType;
    }

    /**
     * @return bool
     */
    public static function laps(): bool
    {
        return self::$laps;
    }

    /**
     * @return bool
     */
    public static function teams(): bool
    {
        return self::$teams;
    }

    /**
     * @return bool
     */
    public static function cup(): bool
    {
        return self::$cup;
    }
}