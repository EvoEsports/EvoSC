<?php


namespace EvoSC\Controllers;


use EvoSC\Interfaces\ControllerInterface;

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
                break;

            case 'Rounds.Script.txt':
            case 'Trackmania/TM_Rounds_Online.Script.txt':
                self::$isRoundsType = true;
                break;

            case 'Laps.Script.txt':
            case 'Trackmania/TM_Laps_Online.Script.Script.txt':
                self::$laps = true;
                self::$isRoundsType = true;
                break;

            case 'Teams.Script.txt':
            case 'Trackmania/TM_Teams_Online.Script.Script.txt':
                self::$teams = true;
                self::$isRoundsType = true;
                break;

            case 'Cup.Script.txt':
            case 'Trackmania/TM_Cup_Online.Script.Script.txt':
                self::$cup = true;
                self::$isRoundsType = true;
                break;
        }
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