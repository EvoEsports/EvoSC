<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Dedi;
use esc\Models\Group;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;
use esc\Models\Song;
use Illuminate\Support\Collection;

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

    private static $pattern;
    private static $chatCommands;

    public static function init()
    {
        self::$chatCommands = collect();

        Server::call('ChatEnableManualRouting', [true, false]);

        HookController::add('PlayerChat', 'ChatController::playerChat');
    }

    public static function getChatCommands(): Collection
    {
        return self::$chatCommands;
    }

    public static function playerChat(Player $player, $text, $isRegisteredCmd)
    {
        if (preg_match(self::$pattern, $text, $matches)) {
            //chat command detected
            if (self::executeChatCommand($player, $text)) {
                return;
            }
        }

        if (preg_match('/^(\/|\/\/|##)/', $text)) {
            //Catch invalid chat commands
            ChatController::message($player, warning('Invalid chat command entered'));
            return;
        }

        Log::logAddLine($player->NickName, $text);
        $nick = $player->NickName;

        $parts = explode(" ", $text);

        foreach ($parts as $part) {
            if (preg_match('/https?:\/\/(?:www\.)?youtube\.com\/.+/', $part, $matches)) {
                $url = $matches[0];
                $info = '$l[' . $url . ']$f44 $ddd' . substr($url, -10) . '$z$s';
                $text = str_replace($url, $info, $text);
            }
        }

        if (preg_match('/([$]+)$/', $text, $matches)) {
            //Escape dollar signs
            $text .= $matches[0];
        }

        if ($player->isSpectator()) {
            $nick = '$eee📷 ' . $nick;
        }

        $chatText = sprintf('$z$s%s$z$s: $%s$z$s%s', $nick, config('color.chat'), $text);

        Server::call('ChatSendServerMessage', [$chatText]);
    }

    private static function executeChatCommand(Player $player, string $text): bool
    {
        $isValidCommand = false;

        //(treat "this is a string" as single argument)
        if (preg_match_all('/\"(.+?)\"/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                //Replace all spaces in quotes to ;:;
                $new = str_replace(' ', ';:;', $match);
                $text = str_replace("\"$match\"", $new, $text);
            }
        }

        //Split input string in arguments
        $arguments = explode(' ', $text);

        foreach ($arguments as $key => $argument) {
            //Change ;:; back to spaces
            $arguments[$key] = str_replace(';:;', ' ', $argument);
        }

        //Find command
        $command = self::$chatCommands
            ->filter(function (ChatCommand $cmd) use ($arguments) {
                return "$cmd->trigger$cmd->command" == strtolower($arguments[0]);
            })
            ->first();

        //Add calling player to beginning of arguments list
        array_unshift($arguments, $player);

        if ($command) {
            //Command exists
            try {
                //Run command callback
                call_func($command->callback, ...$arguments);
            } catch (\Exception $e) {
                Log::logAddLine('ChatController', 'Failed to execute chat command: ' . $e->getTraceAsString(), true);
            }
            $isValidCommand = true;
        }

        return $isValidCommand;
    }

    public static function addCommand(string $command, string $callback, string $description = '-', string $trigger = '/', string $access = null)
    {
        if (!self::$chatCommands) {
            self::$chatCommands = collect();
        }

        $chatCommand = new ChatCommand($trigger, $command, $callback, $description, $access);
        self::$chatCommands->push($chatCommand);

        $triggers = [];
        $chatCommandPattern = '/^(';
        foreach (self::$chatCommands as $cmd) {
            $escapedTrigger = '';
            foreach (str_split($cmd->trigger) as $part) {
                $escapedTrigger .= '\\' . $part;
            }
            array_push($triggers, $escapedTrigger . $cmd->command);
        }
        $chatCommandPattern .= implode('|', $triggers) . ')/i';
        self::$pattern = $chatCommandPattern;

        Log::info("Chat command added: $trigger$command -> $callback", false);
    }

    public static function messageAll(...$parts)
    {
        foreach (onlinePlayers() as $player) {
            self::message($player, ...$parts);
        }
    }

    public static function message(?Player $recipient, ...$parts)
    {
        if (!$recipient || !isset($recipient->Login) || $recipient->Login == null) {
            Log::warning('Do not send message to null player');
            return;
        }

        $icon = "";
        $color = config('color.primary');

        if (preg_match('/\_(\w+)/', $parts[0], $matches)) {
            //set primary color of message
            switch ($matches[1]) {
                case 'secondary':
                    $icon = "";
                    $color = config('color.secondary');
                    break;

                case 'info':
                    $icon = "\$oi\$z";
                    $color = config('color.info');
                    break;

                case 'warning':
                    $icon = "";
                    $color = config('color.warning');
                    break;

                case 'local':
                    $icon = "";
                    $color = config('color.local');
                    break;

                case 'dedi':
                    $icon = "";
                    $color = config('color.dedi');
                    break;

                default:
                    if (preg_match('/[0-9a-f]{3}/', $matches[1])) {
                        $color = $matches[1];
                    } else {
                        $color = config('color.primary');
                    }
            }

            //Remove color code from parts
            array_shift($parts);
        }

        $message = '$s';

        foreach ($parts as $part) {
            if ($part === null) {
                continue;
            }

            if ($part instanceof Player) {
                $message .= $part->NickName;
                continue;
            }

            if ($part instanceof Map) {
                $message .= $part->Name;
                continue;
            }

            if ($part instanceof Group) {
                $part = ucfirst($part->Name);
            }

            if ($part instanceof Module) {
                $message .= secondary(stripAll($part->name));
                continue;
            }

            if ($part instanceof Song) {
                $message .= secondary($part->title);
                continue;
            }

            if ($part instanceof LocalRecord) {
                $message .= secondary($part->Rank) . '. $z$s$' . $color . 'local record $z$s' . secondary(formatScore($part->Score));
                continue;
            }

            if ($part instanceof Dedi) {
                $message .= secondary($part->Rank) . '. $z$s$' . $color . 'dedimania record $z$s' . secondary(formatScore($part->Score));
                continue;
            }

            if (is_float($part) || is_int($part) || preg_match('/\-?\d+\./', $part)) {
                $message .= secondary($part ?? "0");
                continue;
            }

            if (!is_string($part)) {
                echo "CLASS OF PART: ";
                var_dump(get_class($part));
                var_dump(LocalRecord::class);
            }

            $message .= '$z$s$' . $color . $part;

        }

        if (strlen($icon) > 0) {
            $message = '$fff' . $icon . ' ' . $message;
        }

        try {
            Server::chatSendServerMessage($message, $recipient->Login);
        } catch (\Exception $e) {
            Log::logAddLine('ChatController', 'Failed to send message: ' . $e->getMessage());
            Log::logAddLine('', $e->getTraceAsString(), false);
            return;
        }
    }
}