<?php

namespace EvoSC\Modules\PlayerContextMenu;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class PlayerContextMenu extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'sendContextMenu']);
        Hook::add('GroupChanged', [self::class, 'sendContextMenu']);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendContextMenu(Player $player)
    {
        $defaultActions = collect([
            (object)['icon' => '', 'text' => 'Spectate player', 'action' => 'spec', 'access' => '', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Show profile', 'action' => 'profile', 'access' => '', 'confirm' => false],
            (object)['icon' => '', 'text' => 'DM the player', 'action' => 'pm', 'access' => '', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Toggle mute player', 'action' => 'mute', 'access' => 'player_mute', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Ban player', 'action' => 'ban', 'access' => 'player_ban', 'confirm' => true],
            (object)['icon' => '', 'text' => 'Kick player', 'action' => 'kick', 'access' => 'player_kick', 'confirm' => true],
            (object)['icon' => '', 'text' => 'Warn player', 'action' => 'warn', 'access' => 'player_warn', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Set to spectator', 'action' => 'forcespec', 'access' => 'player_force_spec', 'confirm' => false],
        ])->filter(function ($action) use ($player) {
            return $player->hasAccess($action->access);
        });

        Template::show($player, 'PlayerContextMenu.menu', compact('defaultActions'));
    }
}