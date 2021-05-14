<?php


namespace EvoSC\Modules\MatchRounds;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class MatchRounds extends Module implements ModuleInterface
{
    private static int $roundsPerMap = -1;

    public static function start(string $mode, bool $isBoot = false)
    {
        if (ModeController::isTimeAttackType()) {
            return;
        }
        if (Server::getModeScriptSetting('S_RoundsPerMap') <= 0) {
            return;
        }

        self::$roundsPerMap = (int)Server::getModeScriptSetting('S_RoundsPerMap');

        Hook::add('PlayerConnect', [self::class, 'showWidget']);

        self::showWidget();
    }

    public static function showWidget(Player $player = null)
    {
        if (is_null($player)) {
            Template::showAll('MatchRounds.widget', ['roundsPerMap' => self::$roundsPerMap]);
        } else {
            Template::show($player, 'MatchRounds.widget', ['roundsPerMap' => self::$roundsPerMap]);
        }
    }
}