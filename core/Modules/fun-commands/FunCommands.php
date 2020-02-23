<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Controllers\ChatController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class FunCommands implements ModuleInterface
{
    public function __construct()
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

            if (preg_match_all('/\{(.+?)\}/', $message, $matches)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $message = str_replace($matches[0][$i], secondary($matches[1][$i]) . '$z$s$' . config('colors.info'), $message);
                }
            }

            infoMessage($player, ' ', $message)->sendAll();
        }, 'Mimic info output, put text into curly braces to make it secondary-color.');
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}