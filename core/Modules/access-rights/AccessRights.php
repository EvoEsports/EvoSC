<?php


namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\Player;

class AccessRights extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'sendAccessRights']);
    }

    /**
     * @param Player $player
     */
    public static function sendAccessRights(Player $player)
    {
        if ($player->Group == 1) {
            $accessRights = AccessRight::all()->pluck('name')->values();;
        } else {
            $accessRights = $player->group->accessRights()->pluck('name')->values();
        }

        Template::show($player, 'access-rights.update', compact('accessRights'));
    }
}