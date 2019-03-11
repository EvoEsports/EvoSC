<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
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
        $commands = ChatCommand::getCommands()->filter(function (ChatCommand $command) use ($player) {
            if ($command->access) {
                return $player->hasAccess($command->access) && !$command->hidden;
            }

            return !$command->hidden;
        })->map(function (ChatCommand $command) {
            return [
                'command'     => $command->command,
                'description' => $command->description,
                'access'      => $command->access ?: '',
            ];
        })->sortBy('access')->values()->toJson();

        Template::show($player, 'help.window', compact('commands'));
    }
}