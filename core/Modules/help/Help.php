<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Models\Player;

class Help
{
    private static $pages = [
        ['name' => 'Commands', 'action' => 'help,commands'],
        ['name' => 'Keybinds', 'action' => 'help,keybinds'],
    ];

    public function __construct()
    {
        ChatController::addCommand('help', 'Help::showCommandsHelp', 'Show this help');

        ManiaLinkEvent::add('help', 'Help::switchHelp');
    }

    public static function switchHelp(Player $player, $type, $page = 1)
    {
        switch ($type) {
            case 'commands':
                self::showCommandsHelp($player, null, $page);
                break;

            case 'keybinds':
                self::showKeybindsHelp($player, null, $page);
                break;
        }
    }

    public static function showCommandsHelp(Player $player, $help, $page = 1)
    {
        $page = (int)$page;

        $commands = ChatController::getChatCommands()->filter(function (ChatCommand $command) use ($player) {
            if (!$player || !$command) {
                return false;
            }

            return $command->hasAccess($player);
        })->sortBy('trigger');

        $commandsList = Template::toString('help.commands', ['commands' => $commands->forPage($page, 20), 'player' => $player]);
        $pagination = Template::toString('components.pagination', ['pages' => ceil($commands->count() / 20), 'action' => 'help,commands', 'page' => $page]);
        $navigation = self::getNavigation('Commands');

        Template::show($player, 'components.modal', [
            'id' => 'help',
            'title' => 'Help - Chat commands',
            'width' => 180,
            'height' => 97,
            'content' => $commandsList,
            'pagination' => $pagination,
            'navigation' => $navigation,
        ]);
    }

    public static function showKeybindsHelp(Player $player, $help, $page = 1)
    {
        $page = (int)$page;

        $commands = KeyController::getKeybinds()->sortBy('trigger');

        $commandsList = Template::toString('help.keybinds', ['commands' => $commands->forPage($page, 20), 'player' => $player]);
        $pagination = Template::toString('components.pagination', ['pages' => ceil($commands->count() / 20), 'action' => 'help,keybinds', 'page' => $page]);
        $navigation = self::getNavigation('Keybinds');

        Template::show($player, 'components.modal', [
            'id' => 'help',
            'title' => 'Help - Keybinds',
            'width' => 180,
            'height' => 97,
            'content' => $commandsList,
            'pagination' => $pagination,
            'navigation' => $navigation,
        ]);
    }

    private static function getNavigation($active = '')
    {
        return Template::toString('components.navigation', ['pages' => self::$pages, 'active' => $active]);
    }
}