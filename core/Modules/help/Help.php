<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Models\Player;

class Help
{
    public function __construct()
    {
        ChatCommand::add('/help', [Help::class, 'showCommandsHelp'], 'Show this help');

        ManiaLinkEvent::add('help', [Help::class, 'showCommandsHelp']);

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Help', 'help');
        }
    }

    public static function showCommandsHelp(Player $player)
    {
        /*
        $commands = ChatController::getChatCommands()->filter(function (ChatCommand $command) use ($player) {
            return $command->hasAccess($player) && !$command->hidden;
        })->map(function (ChatCommand $command) {
            return [
                'trigger'     => $command->trigger,
                'command'     => $command->command,
                'description' => $command->description,
                'access'      => $command->access ?: '',
            ];
        })->sortBy('trigger')->values()->toJson();

        Template::show($player, 'help.window', compact('commands'));
        */
    }
}