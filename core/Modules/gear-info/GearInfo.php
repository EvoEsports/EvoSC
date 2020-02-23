<?php

namespace esc\Modules;


use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class GearInfo implements ModuleInterface
{
    public function __construct()
    {
        ChatCommand::add('/gear', [self::class, 'show'], 'Enable gear up/down indicator');
    }

    public static function show(Player $player)
    {
        Template::show($player, 'gear-info.meter');
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}