<?php


namespace EvoSC\Modules\AddTime;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ConfigController;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\Votes\Votes;

class AddTime extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet() || !ModeController::isTimeAttackType()) {
            return;
        }

        if (config('added-time-info.enabled')) {
            ConfigController::saveConfig('added-time-info.enabled', false);
            if (!empty(config('added-time-info.buttons'))) {
                ConfigController::saveConfig('add-time.buttons', config('added-time-info.buttons'));
            }
            Log::info('Copied configs from "added-time-info", restarting EvoSC.');
            restart_evosc();
        }

        Hook::add('PlayerConnect', [self::class, 'showWidget']);

        ManiaLinkEvent::add('add_time', [self::class, 'mleAddTime']);

        if (!$isBoot) {
            self::showWidget();
        }
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showWidget(Player $player = null)
    {
        $buttons = collect(config('add-time.buttons'))
            ->sort()
            ->reverse()
            ->values();

        if (is_null($player)) {
            Template::showAll('AddTime.widget', compact('buttons'));
        } else {
            Template::show($player, 'AddTime.widget', compact('buttons'));
        }
    }

    /**
     * @param Player $player
     * @param $minutes
     */
    public static function mleAddTime(Player $player, $minutes)
    {
        Votes::cmdAskMoreTime($player, null, $minutes);
    }
}