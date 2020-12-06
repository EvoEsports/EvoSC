<?php


namespace EvoSC\Modules\SocialMedia;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class SocialMedia extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'showWidget']);
    }

    public static function showWidget(Player $player)
    {
        $links = collect(config('social-media'))->map(function ($link) {
            if (preg_match('/^\$(.+)$/', $link->color, $matches)) {
                $link->color = config($matches[1]);
            }

            return $link;
        })->reverse()->values();

        Template::show($player, 'SocialMedia.widget', compact('links'));
    }
}