<?php


namespace EvoSC\Modules\MatchMakerWidget;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\TeamController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

class MatchMakerWidget extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (!ModeController::isRoundsType()) {
            return;
        }

        AccessRight::add('match_maker', 'Control matches and view the admin panel for it.');

        ManiaLinkEvent::add('toggle_horns', [self::class, 'mleToggleHorns'], 'match_maker');
        ManiaLinkEvent::add('balance_teams', [self::class, 'mleToggleTeamBalance'], 'match_maker');
        ManiaLinkEvent::add('show_teams_setup', [self::class, 'mleShowTeamsSetup'], 'match_maker');
        ManiaLinkEvent::add('setup_teams', [self::class, 'mleSetupTeams'], 'match_maker');
        ManiaLinkEvent::add('change_point_team', [self::class, 'mleChangeTeamPoint'], 'match_maker');

        Hook::add('PlayerConnect', [self::class, 'showWidget']);

        if (!$isBoot) {
            $hornsEnabled = !Server::areHornsDisabled();
            foreach (accessPlayers('match_maker') as $player) {
                Template::show($player, 'MatchMakerWidget.widget', compact('hornsEnabled'));
            }
        }
    }

    /**
     * @param Player $player
     * @param $teamId
     * @param $points
     */
    public static function mleChangeTeamPoint(Player $player, $teamId, $points)
    {
        $team = intval($teamId);
        $points = intval($points);

        if ($points == 0) {
            return;
        }

        Server::triggerModeScriptEventArray('Trackmania.GetScores');
        Hook::add('Scores', function ($data) use ($player, $team, $points) {
            $action = $points < 0 ? ' removed ' : ' added ';
            $direction = $points < 0 ? ' from ' : ' to ';
            $roundPoints = $data->teams[$team]->roundpoints;
            $mapPoints = $data->teams[$team]->mappoints;
            $matchPoints = $data->teams[$team]->matchpoints;
            $teamName = $data->teams[$team]->name;

            $mapPoints += $points;
            $matchPoints += $points;
            $points = abs($points);

            Server::triggerModeScriptEventArray('Trackmania.SetTeamPoints', ["$team", "$roundPoints", "$mapPoints", "$matchPoints"]);

            warningMessage($player, $action, secondary("$points points"), $direction, secondary("Team $teamName"))->sendAll();
        }, true);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function mleShowTeamsSetup(Player $player)
    {
        Template::show($player, 'MatchMakerWidget.team-setup');
    }

    /**
     * @param Player $player
     * @param \stdClass|null $data
     */
    public static function mleSetupTeams(Player $player, \stdClass $data = null)
    {
        Server::setForcedClubLinks(TeamController::getClubLinkUrl($data->name[0], $data->primary[0], $data->secondary[0], $data->emblem[0]),
            TeamController::getClubLinkUrl($data->name[1], $data->primary[1], $data->secondary[1], $data->emblem[1]));

        $settings = Server::getModeScriptSettings();
        $settings['S_UseClublinks'] = true;
        Server::setModeScriptSettings($settings);

        infoMessage($player, ' updated the team information.')->sendAll();
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showWidget(Player $player)
    {
        if (!$player->hasAccess('match_maker')) {
            return;
        }

        $hornsEnabled = !Server::areHornsDisabled();

        Template::show($player, 'MatchMakerWidget.widget', compact('hornsEnabled'));
    }

    /**
     * @param Player $player
     */
    public static function mleToggleHorns(Player $player)
    {
        if (Server::areHornsDisabled()) {
            Server::disableHorns(false);
            successMessage($player, ' enabled horns, happy honking!')->sendAll();
        } else {
            Server::disableHorns(true);
            dangerMessage($player, ' disabled horns.')->sendAll();
        }
    }

    /**
     * @param Player $player
     */
    public static function mleToggleTeamBalance(Player $player)
    {
        infoMessage($player, ' balances the teams.')->sendAll();
        Server::autoTeamBalance();
    }
}