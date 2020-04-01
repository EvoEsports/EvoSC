<?php


namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class ASay extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('//asay', [self::class, 'cmdDisplayMessage'], 'Display a message at the center of the screen. Leave message empty to clear.', 'info_messages');
    }

    public static function cmdDisplayMessage(Player $player, string $cmd, ...$message)
    {
        $text = implode(' ', $message);

        Template::showAll('asay.window', compact('text'));
    }
}