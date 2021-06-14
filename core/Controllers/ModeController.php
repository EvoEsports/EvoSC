<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Classes\Timer;
use EvoSC\Commands\EscRun;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\QuickButtons\QuickButtons;

class ModeController implements ControllerInterface
{
    private static bool $isTimeAttackType;
    private static bool $isRoundsType;
    private static bool $laps;
    private static bool $teams;
    private static bool $cup;
    private static bool $royal;
    private static string $mode;

    private static int $warmUpNb = 0;
    private static int $warmUpRound = 0;

    /**
     *
     */
    public static function init()
    {
        AccessRight::add('warm_up_skip', 'Lets you skip the warm-up phase.');
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        self::setMode($mode);

        Hook::add('WarmUpEnd', [self::class, 'warmUpEnd']);
        Hook::add('Trackmania.WarmUp.StartRound', [self::class, 'warmUpRoundStarted']);

        ManiaLinkEvent::add('warmup.skip', [self::class, 'skipWarmUp'], 'warm_up_skip');
    }

    public static function warmUpRoundStarted()
    {
        infoMessage('Warm-up ', secondary(++self::$warmUpRound . "/" . self::$warmUpNb), ' started.')
            ->setColor('f90')
            ->setIcon(' ')
            ->sendAll();

        Hook::fire('WarmUpRoundStarted', self::$warmUpRound, self::$warmUpNb);
    }

    public static function warmUpEnd()
    {
        self::$warmUpRound = 0;

        infoMessage('Warm-up ended, ', secondary('starting play-loop.'))
            ->setColor('f90')
            ->setIcon(' ')
            ->sendAll();
    }

    public static function skipWarmUp(Player $player)
    {
        Server::triggerModeScriptEventArray('Trackmania.WarmUp.ForceStop', []);
        infoMessage($player, ' skips warm-up.')->setColor('f90')->sendAll();
    }

    /**
     * @param string $mode
     */
    public static function setMode(string $mode)
    {
        self::$mode = $mode;
        self::$teams = false;
        self::$laps = false;
        self::$cup = false;
        self::$royal = false;
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
            case 'Trackmania/TM_Laps_Online.Script.txt':
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
            case 'Trackmania/TM_Cup_Online.Script.txt':
                self::$isTimeAttackType = false;
                self::$isRoundsType = true;
                self::$cup = true;
                break;

            case 'Trackmania/TM_RoyalTimeAttack_Online.Script.txt':
            case 'Trackmania/Evo_Royal_TA.Script.txt':
                self::$isTimeAttackType = true;
                self::$royal = true;
                break;
        }

        if (self::$isRoundsType) {
            $wus = Server::getModeScriptSettings()['S_WarmUpNb'];

            if (is_null($wus)) {
                $wus = 0;
            }

            self::$warmUpNb = $wus;
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

    /**
     * @return string
     */
    public static function getMode(): string
    {
        return self::$mode;
    }

    /**
     * @return int
     */
    public static function getWarmUpRound(): int
    {
        return self::$warmUpRound;
    }

    /**
     * @return int
     */
    public static function getWarmUpRoundCount(): int
    {
        return self::$warmUpNb;
    }

    /**
     * @return bool
     */
    public static function isRoyal(): bool
    {
        return self::$royal;
    }
}