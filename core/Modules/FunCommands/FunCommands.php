<?php

namespace EvoSC\Modules\FunCommands;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Controllers\ChatController;
use EvoSC\Controllers\PlayerController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class FunCommands extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('/afk', function (Player $player) {
            ChatController::playerChat($player, '$oAway from keyboard.');
            Server::forceSpectator($player->Login, 3);
        }, 'Go AFK.');

        ChatCommand::add('/gg', function (Player $player) {
            ChatController::playerChat($player, '$oGood Game');
        }, 'Say Good Game.');

        ChatCommand::add('/gga', function (Player $player) {
            ChatController::playerChat($player, '$oGood Game All');
        }, 'Say Good Game All.');

        ChatCommand::add('/bootme', function (Player $player) {
            infoMessage($player, ' boots back to the real world!')->sendAll();
            Server::kick($player->Login, 'cya');
        }, 'Boot yourself back to the real world.');

        ChatCommand::add('/me', function (Player $player, ...$message) {
            array_shift($message);

            $message = trim(implode(' ', $message));

            if (stripAll($message) == '') {
                return;
            }

            $parts = explode(' ', $message);
            foreach ($parts as $part) {
                if (preg_match('/@(.+)/', $part, $matches)) {
                    $target = PlayerController::findPlayerByName($player, $matches[1]);
                    if ($target) {
                        $message = str_replace($part, '$<' . secondary($target->NickName) . '$>', $message);
                    } else {
                        warningMessage('Player ', secondary($matches[1]), ' not found.')->send($player);
                        return;
                    }
                }
            }

            if (preg_match_all('/{(.+?)}/', $message, $matches)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $message = str_replace($matches[0][$i], '$<' . secondary($matches[1][$i]) . '$>', $message);
                }
            }

            infoMessage($player, ' ', $message)->sendAll();
        }, 'Print a info-message beginning with your name.');
    }
}