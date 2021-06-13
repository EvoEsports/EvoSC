<?php


namespace EvoSC\Modules\GameModeChanger;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

class GameModeChanger extends Module implements ModuleInterface
{
    private static array $gameModesManiaplanet = [
        'TimeAttack' => 'TimeAttack.Script.txt',
        'Rounds' => 'Rounds.Script.txt',
        'Team' => 'Team.Script.txt',
        'Cup' => 'Cup.Script.txt',
        'Laps' => 'Laps.Script.txt',
        'Chase' => 'Chase.Script.txt',
    ];

    private static array $gameModesTrackmania = [
        'TimeAttack' => 'Trackmania/TM_TimeAttack_Online.Script.txt',
        'Rounds' => 'Trackmania/TM_Rounds_Online.Script.txt',
        'Teams' => 'Trackmania/TM_Teams_Online.Script.txt',
        'Cup' => 'Trackmania/TM_Cup_Online.Script.txt',
        'Laps' => 'Trackmania/TM_Laps_Online.Script.txt',
        'Champion' => 'Trackmania/TM_Champion_Online.Script.txt',
        'Knockout' => 'Trackmania/TM_Knockout_Online.Script.txt',
    ];

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::add('change_mode', 'Allows to change the current mode.');

        ChatCommand::add('//mode', [self::class, 'cmdChangeGameMode'], 'Select a different mode.', 'change_mode');

        ManiaLinkEvent::add('game_mode.select', [self::class, 'mleSelectMode'], 'change_mode');
    }

    /**
     * @param Player $player
     * @param null $cmd
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function cmdChangeGameMode(Player $player, $cmd = null)
    {
        if (isManiaPlanet()) {
            $options = self::$gameModesManiaplanet;
        } else {
            $options = self::$gameModesTrackmania;
        }

        Template::show($player, 'GameModeChanger.select', ['options' => $options]);
    }

    /**
     * @param Player $player
     * @param $name
     * @param $gameModeId
     */
    public static function mleSelectMode(Player $player, $name, $gameModeId)
    {
        try {
            Server::setScriptName($gameModeId);
            warningMessage($player, ' changed the game-mode to ', secondary($name))->sendAll();
            Server::restartMap();
            ModeController::rebootModules();
        } catch (\Exception $e) {
            Log::errorWithCause('Failed to change mode', $e);
            dangerMessage($e->getMessage())->send($player);
        }
    }
}
