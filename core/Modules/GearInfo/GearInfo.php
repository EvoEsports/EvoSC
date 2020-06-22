<?php

namespace EvoSC\Modules\GearInfo;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class GearInfo extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('/gear', [self::class, 'show'], 'Enable gear up/down indicator');
    }

    public static function show(Player $player)
    {
        Template::show($player, 'GearInfo.meter');
    }
}