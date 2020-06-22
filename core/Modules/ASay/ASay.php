<?php


namespace EvoSC\Modules\ASay;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

class ASay extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::add('info_messages', 'Add/edit/remove reccuring info-messages.');

        ChatCommand::add('//asay', [self::class, 'cmdDisplayMessage'], 'Display a message at the center of the screen. Leave message empty to clear.', 'info_messages');
    }

    public static function cmdDisplayMessage(Player $player, string $cmd, ...$message)
    {
        $text = implode(' ', $message);

        Template::showAll('ASay.window', compact('text'));
    }
}