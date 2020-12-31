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
    public static function start(string $mode, bool $isBoot = false)
    {
        if (ModeController::isTimeAttackType()) {
            return;
        }
        if (Server::getModeScriptVariable('S_RoundsPerMap') <= 0) {
            return;
        }

        Hook::add('PlayerConnect', [self::class, 'showWidget']);
        Hook::add('PlayerConnect', [self::class, 'showWidget']);

        if (!$isBoot) {
            self::showWidget();
        }
    }

    public static function showWidget(Player $player = null)
    {
        $roundsPerMap = Server::getModeScriptVariable('S_RoundsPerMap');

        if (is_null($player)) {
            Template::showAll('MatchRounds.widget', compact('roundsPerMap'));
        } else {
            Template::show($player, 'MatchRounds.widget', compact('roundsPerMap'));
        }
    }
}