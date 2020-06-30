<?php


namespace EvoSC\Controllers;


use EvoSC\Interfaces\ControllerInterface;

class ModeController implements ControllerInterface
{
    private static bool $isTimeAttack;
    private static bool $isRounds;

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
        switch ($mode) {
            case 'TimeAttack.Script.txt':
            case 'Trackmania/TM_TimeAttack_Online.Script.txt':
                self::$isRounds = false;
                self::$isTimeAttack = true;
                break;

            case 'Rounds.Script.txt':
            case 'Trackmania/TM_Rounds_Online.Script.txt':
                self::$isRounds = true;
                self::$isTimeAttack = false;
                break;
        }
    }

    /**
     * @return bool
     */
    public static function isRounds(): bool
    {
        return self::$isRounds;
    }

    /**
     * @return bool
     */
    public static function isTimeAttack(): bool
    {
        return self::$isTimeAttack;
    }
}