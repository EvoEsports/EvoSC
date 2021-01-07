<?php


namespace EvoSC\Modules\SetName;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\PlayerController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class SetName extends Module implements ModuleInterface
{
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet()) {
            return;
        }

        ChatCommand::add('/setname', [self::class, 'cmdSetName'], 'Change NickName.');

        ManiaLinkEvent::add('save_nickname', [self::class, 'mleSaveNickname']);
    }

    /**
     * @param Player $player
     * @param $cmd
     * @param mixed ...$name
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function cmdSetName(Player $player, $cmd, ...$name)
    {
        $name = str_replace("\n", '', trim(implode(' ', $name)));
        self::showSetNickname($player, $name);
    }

    /**
     * @param Player $player
     * @param $name
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showSetNickname(Player $player, $name)
    {
        Template::show($player, 'SetName.window', compact('name'));
    }

    /**
     * @param Player $player
     * @param \stdClass $data
     */
    public static function mleSaveNickname(Player $player, ...$nickname)
    {
        $name = str_replace("\n", '', trim(implode(' ', $nickname)));
        PlayerController::setName($player, $name);
    }
}