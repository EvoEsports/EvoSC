<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\Module;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Dedi;
use esc\Models\Group;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;
use esc\Models\Song;
use Illuminate\Database\Eloquent\Collection;

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
        self::$chatCommands = new Collection();

        Server::call('ChatEnableManualRouting', [true, false]);

        HookController::add('PlayerChat', 'esc\Controllers\ChatController::playerChat');

        Template::add('help', File::get('core/Templates/help.latte.xml'));

        self::addCommand('help', '\esc\Controllers\ChatController::showHelp', 'Show this help');
    }

    private static function getChatCommands(): Collection
    {
        return self::$chatCommands;
    }

    public static function showHelp(Player $player, $cmd, $page = 1)
    {
        $page = (int)$page;

        $commands = self::getChatCommands()->filter(function (ChatCommand $command) use ($player) {
            if (!$player || !$command) {
                return false;
            }

            return $command->hasAccess($player);
        })->sortBy('trigger')->forPage($page, 23);

        $commandsList = Template::toString('help', ['commands' => $commands, 'player' => $player]);
        $pagination = Template::toString('esc.pagination', ['pages' => $commands->count() / 23, 'action' => 'help.show', 'page' => $page]);

        Template::show($player, 'esc.modal', [
            'id' => 'Help',
            'width' => 180,
            'height' => 97,
            'content' => $commandsList,
            'pagination' => $pagination
        ]);
    }

    public static function playerChat(Player $player, $text, $isRegisteredCmd)
    {
        if (preg_match(self::$pattern, $text, $matches)) {
            if (self::executeChatCommand($player, $text)) {
                return;
            }
        }

        if (preg_match('/^(\/|\/\/|##)/', $text)) {
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

        if (preg_match('/\$l\[(http.+)\](http.+)[ ]?/', $text, $matches)) {
            if (strlen($matches[2]) > 50) {
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
            $nick = '$eee📷 ' . $nick;
        }

        $chatText = sprintf('$z$s%s$z$s: $%s$z$s%s', $nick, config('color.chat'), $text);

//        echo stripAll("$chatText\n");

        Server::call('ChatSendServerMessage', [$chatText]);
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

        foreach ($arguments as $key => $argument) {
            $arguments[$key] = str_replace(';:;', ' ', $argument);
        }

        $command = self::$chatCommands
            ->filter(function (ChatCommand $cmd) use ($arguments) {
                return "$cmd->trigger$cmd->command" == $arguments[0];
            })
            ->first();

        array_unshift($arguments, $player);

        if ($command) {
            try {
                call_user_func_array($command->callback, $arguments);
            } catch (\Exception $e) {
                Log::logAddLine('ChatController', 'Failed to execute chat command: ' . $e->getTraceAsString(), true);
            }
            $isValidCommand = true;
        }

        return $isValidCommand;
    }

    public static function addCommand(string $command, string $callback, string $description = '-', string $trigger = '/', string $access = null)
    {
        $chatCommand = new ChatCommand($trigger, $command, $callback, $description, $access);
        self::$chatCommands->add($chatCommand);

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

        try{
            Server::chatSendServerMessage($message, $recipient->Login);
        }catch(\Exception $e){
            Log::logAddLine('ChatController', 'Failed to send message: ' . $e->getMessage());
            Log::logAddLine('', $e->getTraceAsString(), false);
            return;
        }
    }
}