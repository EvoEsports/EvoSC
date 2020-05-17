<?php

namespace EvoSC\Modules\WebsiteButton;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class WebsiteButton extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (config('website.url')) {
            Hook::add('PlayerConnect', [self::class, 'show']);
        }
    }

    public static function show(Player $player)
    {
        Template::show($player, 'WebsiteButton.widget');
    }
}