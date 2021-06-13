<?php


namespace EvoSC\Modules\AccessRights;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

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
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendAccessRights(Player $player)
    {
        if ($player->group->unrestricted) {
            $accessRights = AccessRight::all()->pluck('name')->values();
        } else {
            $accessRights = $player->group->accessRights()->pluck('name')->values();
        }

        Template::show($player, 'AccessRights.update', compact('accessRights'), false, 20);
    }
}