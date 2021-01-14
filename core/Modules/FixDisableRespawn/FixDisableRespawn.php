<?php


namespace EvoSC\Modules\FixDisableRespawn;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use Illuminate\Support\Collection;

class FixDisableRespawn extends Module implements ModuleInterface
{
    private static bool $disableRespawn;

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (!ModeController::isRoundsType()) {
            return;
        }

        self::beginMatch();

        Hook::add('Trackmania.Event.Respawn', [self::class, 'playerRespawn']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);
    }

    /**
     *
     */
    public static function beginMatch()
    {
        self::$disableRespawn = Server::getDisableRespawn()['NextValue'];
    }

    /**
     * @param $data
     */
    public static function playerRespawn($data)
    {
        if (ModeController::isWarmUpInProgress() || !self::$disableRespawn) {
            return;
        }

        $login = json_decode($data[0])->login;
        Server::forceSpectator($login, 3);
        usleep(10000);
        Server::forceSpectator($login, 2);
    }
}