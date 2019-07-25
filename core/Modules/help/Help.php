<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class Help implements ModuleInterface
{
    public function __construct()
    {
        self::up('TimeAttack');

        ManiaLinkEvent::add('help', [Help::class, 'showCommandsHelp']);
        ManiaLinkEvent::add('help.show_cmds', [Help::class, 'showCommandsHelp']);
        ManiaLinkEvent::add('help.show_about', [Help::class, 'showAbout']);

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

        Template::show($player, 'help.cmds', compact('commands'));
    }

    public static function showAbout(Player $player)
    {
        Template::show($player, 'help.about');
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function up(string $mode)
    {
        switch ($mode){
            default:
                ChatCommand::add('/help', [Help::class, 'showCommandsHelp'], 'Show this help');
                ChatCommand::add('/about', [Help::class, 'showAbout'], 'Show information about the server-controller.');
            case 'TimeAttack':
        }
    }

    /**
     * Called when the module is unloaded
     */
    public static function down()
    {
        // TODO: Implement down() method.
    }
}