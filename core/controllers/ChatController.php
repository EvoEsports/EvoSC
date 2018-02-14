<?php

namespace esc\controllers;


use esc\classes\ChatCommand;
use esc\classes\Log;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\FaultException;

class ChatController
{
    /* maniaplanet chat styling
$i: italic
$s: shadowed
$w: wide
$n: narrow
$m: normal
$g: default color
$o: bold
$z: reset all
$t: Changes the text to capitals
$$: Writes a dollarsign
     */

    private static $triggers;
    private static $chatCommands;

    public static function initialize()
    {
        self::$triggers = [];
        self::$chatCommands = new Collection();

        RpcController::call('ChatEnableManualRouting', [true, false]);

        HookController::add('PlayerChat', 'esc\controllers\ChatController::playerChat');

        self::addCommand('help', '\esc\controllers\ChatController::showHelp', 'Show this help');
    }

    public static function showHelp(Player $player)
    {
        foreach (self::$chatCommands as $chatCommand) {
            self::message($player, $chatCommand->getHelp());
        }
    }

    public static function playerChat(Player $player, $text, $isRegisteredCmd)
    {
        if (in_array(substr($text, 0, 1), self::$triggers)) {
            if (self::executeChatCommand($player, $text)) {
                return;
            }
        }

        Log::chat($player->NickName, $text);

        $nick = $player->NickName;
//        $nick = preg_replace('/\$[0-9a-f]{3}/', '', $player->NickName);

        $nick = '$18f' . $nick;

        //18f-Guest 1f3-SA

        if (preg_match('/\$l\[(http.+)\](http.+)[ ]?/', $text, $matches)) {
            if (strlen($matches[2]) > 40) {
                $restOfString = explode(' ', $matches[2]);
                $urlName = array_shift($restOfString);
                $short = substr($urlName, 0, 28) . '..' . substr($urlName, -10);
                $short = preg_replace('/^https?:\/\//', '', $short);
                if (preg_match('/^https/', $matches[1])) {
                    $short = "🔒" . $short;
                }
                $newUrl = $short . '$z $s' . implode(' ', $restOfString);
                $text = str_replace("\$l[$matches[1]]$matches[2]", "\$l[$matches[1]]$newUrl", $text);
            }
        }

        if (preg_match('/([$]+)$/', $text, $matches)) {
            $text .= $matches[0];
        }

        $text = preg_replace('/\$[nb]/', '', $text);

        if ($player->isSpectator()) {
            $nick = '$eee📷$z ' . $nick;
        }

        $chatText = sprintf('$z$s%s: $fe2$z$s%s', $nick, $text);

        echo "$chatText\n";

        RpcController::call('ChatSendServerMessage', [$chatText]);
    }

    private static function executeChatCommand(Player $player, string $text): bool
    {
        $isValidCommand = false;

        if (preg_match_all('/\"(.+?)\"/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $new = str_replace(' ', ';:;', $match);
                $text = str_replace("\"$match\"", $new, $text);
            }
        }

        $arguments = explode(' ', $text);
        $trigger = substr($arguments[0], 0, 1);
        $cmd = substr($arguments[0], 1);

        foreach ($arguments as $key => $argument) {
            $arguments[$key] = str_replace(';:;', ' ', $argument);
        }

        $command = self::$chatCommands
            ->where('command', $cmd)
            ->where('trigger', $trigger)
            ->first();

        array_unshift($arguments, $player);

        if ($command) {
            call_user_func_array($command->callback, $arguments);
            $isValidCommand = true;
        }

        return $isValidCommand;
    }

    public static function addCommand(string $command, string $callback, string $description = '-', string $trigger = '/')
    {
        if (strlen($trigger) != 1) {
            Log::error('Trigger must be one character.');
        }

        if (!in_array($trigger, self::$triggers)) {
            array_push(self::$triggers, $trigger);
        }

        $chatCommand = new ChatCommand($trigger, $command, $callback, $description);
        self::$chatCommands->add($chatCommand);

        Log::info("Chat command added: $trigger $command -> $callback");
    }

    public static function messageAll(string $message)
    {
        try {
            RpcController::getRpc()->chatSendServerMessage('$18f' . $message);
        } catch (FaultException $e) {
            Log::error($e);
        }
    }

    public static function message(Player $player, string $message)
    {
        try {
            RpcController::getRpc()->chatSendServerMessage($message, $player->Login);
        } catch (FaultException $e) {
            Log::error($e);
        }
    }
}