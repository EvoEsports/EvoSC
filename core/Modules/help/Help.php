<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Models\Player;

class Help
{
    public function __construct()
    {
        ChatController::addCommand('help', 'Help::show', 'Show this help');
        ManiaLinkEvent::add('help.show', 'Help::show');
    }

    public static function show(Player $player, $help, $page = 1)
    {
        $page = (int)$page;

        $commands = ChatController::getChatCommands()->filter(function (ChatCommand $command) use ($player) {
            if (!$player || !$command) {
                return false;
            }

            return $command->hasAccess($player);
        })->sortBy('trigger');

        $commandsList = Template::toString('help.help', ['commands' => $commands->forPage($page, 21), 'player' => $player]);
        $pagination = Template::toString('components.pagination', ['pages' => ceil($commands->count() / 21), 'action' => 'help.show,cmd', 'page' => $page]);

        Template::show($player, 'components.modal', [
            'id' => 'Help',
            'width' => 180,
            'height' => 97,
            'content' => $commandsList,
            'pagination' => $pagination
        ]);
    }
}