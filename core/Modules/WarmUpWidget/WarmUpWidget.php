<?php


namespace EvoSC\Modules\WarmUpWidget;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class WarmUpWidget extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded.
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (ModeController::isTimeAttackType()) {
            return;
        }

        Hook::add('PlayerConnect', [self::class, 'sendWarmUpWidget']);
        Hook::add('WarmUpEnd', [self::class, 'warmUpEnd']);
        Hook::add('WarmUpRoundStarted', [self::class, 'warmUpRoundStarted']);
    }

    /**
     * Display the warm up widget for newly connected players, while warm up is in progress.
     *
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendWarmUpWidget(Player $player)
    {
        if (Server::getWarmUp()) {
            Template::show($player, 'WarmUpWidget.widget', [
                'warmupNb' => ModeController::getWarmUpRoundCount(),
                'round' => ModeController::getWarmUpRound()
            ]);
        }
    }

    /**
     * Update the widget.
     *
     * @param int $round
     * @param int $warmUpCount
     */
    public static function warmUpRoundStarted(int $round, int $warmUpCount)
    {
        Template::showAll('WarmUpWidget.widget', [
            'warmupNb' => $warmUpCount,
            'round' => $round
        ]);
    }

    /**
     * Tell the ManiaLink that the warm up ended, so it can hide.
     */
    public static function warmUpEnd()
    {
        Template::showAll('WarmUpWidget.widget', ['warmUpEnded' => true, 'warmupNb' => 0, 'round' => 0]);
    }
}