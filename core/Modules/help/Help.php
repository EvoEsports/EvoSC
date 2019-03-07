<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class Help
{
    public function __construct()
    {
        ChatController::addCommand('help', [Help::class, 'showCommandsHelp'], 'Show this help');

        ManiaLinkEvent::add('help', [Help::class, 'switchHelp']);

        KeyController::createBind('X', [self::class, 'reload']);

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Help', 'help,commands,1');
        }
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::showCommandsHelp($player);
    }

    public static function showCommandsHelp(Player $player)
    {
        $commands = ChatController::getChatCommands()->filter(function (ChatCommand $command) use ($player) {
            return $command->hasAccess($player);
        })->map(function (ChatCommand $command) {
            return [
                'trigger'     => $command->trigger,
                'command'     => $command->command,
                'description' => $command->description,
                'access'      => $command->access ?: '',
            ];
        })->sortBy('trigger')->values()->toJson();

        Template::show($player, 'help.window', compact('commands'));
    }
}