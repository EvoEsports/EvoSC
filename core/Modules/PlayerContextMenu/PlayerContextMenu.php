<?php

namespace EvoSC\Modules\PlayerContextMenu;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Exceptions\InvalidArgumentException;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;

class PlayerContextMenu extends Module implements ModuleInterface
{
    /**
     * @var int
     */
    protected int $bootPriority = Module::PRIORITY_HIGHEST;

    /**
     * @var Collection|null
     */
    private static ?Collection $customActions;

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$customActions = collect();

        Hook::add('PlayerConnect', [self::class, 'sendContextMenu']);
        Hook::add('GroupChanged', [self::class, 'sendContextMenu']);
    }

    /**
     * @param Player $player
     * @return void
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendContextMenu(Player $player): void
    {
        $topActions = collect([
            (object)['icon' => '', 'text' => 'Spectate', 'action' => '__specPlayer', 'access' => '', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Profile', 'action' => '__showProfile', 'access' => '', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Message', 'action' => 'pm.dialog', 'access' => '', 'confirm' => false],
        ])->values();

        $defaultActions = collect([
            (object)['icon' => '', 'text' => 'Mute player (toggle)', 'action' => 'mute', 'access' => 'player_mute', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Ban player', 'action' => 'ban', 'access' => 'player_ban', 'confirm' => true],
            (object)['icon' => '', 'text' => 'Kick player', 'action' => 'kick', 'access' => 'player_kick', 'confirm' => true],
            (object)['icon' => '', 'text' => 'Warn player', 'action' => 'warn', 'access' => 'player_warn', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Set to spectator', 'action' => 'forcespec', 'access' => 'player_force_spec', 'confirm' => false],
            (object)['icon' => '', 'text' => 'Reset to UbiName', 'action' => 'reset_nickname', 'access' => 'player_reset_name', 'confirm' => true],
            (object)['icon' => '', 'text' => 'Block setname & reset', 'action' => 'reset_nickname_and_block', 'access' => 'player_reset_name', 'confirm' => true],
            (object)['icon' => '', 'text' => 'Allow setname', 'action' => 'allow_setname', 'access' => 'player_reset_name', 'confirm' => true],
        ])
            ->merge(self::$customActions)
            ->filter(function ($action) use ($player) {
                return $player->hasAccess($action->access);
            })
            ->values();

        Template::show($player, 'PlayerContextMenu.menu', compact('topActions', 'defaultActions'));
    }

    /**
     * @param string $icon
     * @param string $text
     * @param string $action
     * @param string $access
     * @param bool $confirm
     * @return void
     * @throws InvalidArgumentException
     */
    public static function extend(string $icon, string $text, string $action, string $access = '', bool $confirm = false): void
    {
        if(empty($action)){
            throw new InvalidArgumentException("Action can not be empty.");
        }

        self::$customActions->push((object)['icon' => $icon, 'text' => $text, 'action' => $action, 'access' => $access, 'confirm' => $confirm]);
    }
}