<?php


namespace EvoSC\Modules\GameModeChanger;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Module;
use EvoSC\Classes\Question;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use Exception;

class GameModeChanger extends Module implements ModuleInterface
{
    private static array $gameModes = [
        'Script', 'Rounds', 'TimeAttack', 'Team', 'Laps', 'Cup', 'Stunts'
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
    }

    /**
     * @param Player $player
     * @param $cmd
     * @param int $mode
     */
    public static function cmdChangeGameMode(Player $player, $cmd)
    {
        $modes = '';
        foreach (self::$gameModes as $key => $mode) {
            $modes .= "\$ff0[$key] \$fff$mode, ";
        }
        $modes = substr($modes, 0, -2);
        Question::ask("Please select a mode:\n" . secondary($modes), $player, function (Player $player, $answer) {
            self::selectGameMode(intval($answer), $player);
        });
    }

    /**
     * @param int $modeId
     * @param Player $player
     */
    private static function selectGameMode(int $modeId, Player $player)
    {
        if ($modeId < -1 || $modeId >= count(self::$gameModes)) {
            warningMessage('Invalid mode selected.')->send($player);
            return;
        }

        try {
            Server::setGameMode($modeId);
            infoMessage($player, ' changed game mode to ', secondary(self::$gameModes[$modeId]))->sendAll();
        } catch (Exception $e) {
            dangerMessage($e->getMessage())->send($player);
        }
    }
}