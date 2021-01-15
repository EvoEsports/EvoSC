<?php


namespace EvoSC\Modules\ForceTeam;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use EvoSC\Modules\QuickButtons\QuickButtons;

class ForceTeam extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::add('switch_team', 'Switch players to another team.');

        if (!ModeController::teams()) {
            return;
        }

        QuickButtons::addButton('', 'Switch player team', 'show_switch_player_team', 'switch_team');

        ChatCommand::add('//switchplayer', [self::class, 'cmdSwitchPlayer'], 'Force players to another team.', 'switch_team');

        ManiaLinkEvent::add('show_switch_player_team', [self::class, 'showWindow'], 'switch_team');
        ManiaLinkEvent::add('switch_player_team', [self::class, 'mleSwitchPlayerTeam'], 'switch_team');
    }

    /**
     * @param Player $player
     * @param $cmd
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function cmdSwitchPlayer(Player $player, $cmd)
    {
        self::showWindow($player);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showWindow(Player $player)
    {
        $teams = onlinePlayers()->groupBy('team');
        $teamInfo = [Server::getTeamInfo(1), Server::getTeamInfo(2)];

        Template::show($player, 'ForceTeam.window', compact('teams', 'teamInfo'));
    }

    /**
     * @param Player $admin
     * @param $login
     * @param $targetTeam
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function mleSwitchPlayerTeam(Player $admin, $login, $targetTeam)
    {
        $team = intval($targetTeam);
        Server::forcePlayerTeam($login, $team);
        $teamName = Server::getTeamInfo($team + 1)->name;
        infoMessage($admin, ' moved ', player($login), ' to ', secondary('$' . Server::getTeamInfo($team + 1)->rGB . "Team $teamName"))->sendAll();
    }
}