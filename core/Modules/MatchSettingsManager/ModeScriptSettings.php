<?php


namespace EvoSC\Modules\MatchSettingsManager;


use EvoSC\Modules\MatchSettingsManager\Classes\ModeScriptSetting;
use Illuminate\Support\Collection;

/**
 * Class ModeScriptSettings
 *
 * Get available mode script settings and their defaults.
 * From: https://doc.maniaplanet.com/dedicated-server/references/settings-list-for-nadeo-gamemodes
 *
 * @package EvoSC\Modules\MatchSettingsManager
 */
class ModeScriptSettings
{
    /**
     * @return Collection
     */
    public static function all(): Collection
    {
        return collect([
            new ModeScriptSetting('S_ChatTime', 'integer', 'Chat time at the end of a map or match', 10),
            new ModeScriptSetting('S_UseClublinks', 'boolean', 'Use the players clublinks, or otherwise use the default teams', false),
            new ModeScriptSetting('S_UseClublinksSponsors', 'boolean', 'Display the clublinks sponsors', false),
            new ModeScriptSetting('S_NeutralEmblemUrl', 'text', 'Url of the neutral emblem url to use by default', ''),
            new ModeScriptSetting('S_ScriptEnvironment', 'text', 'Environment in which the script runs, used mainly for debugging purpose', 'production'),
            new ModeScriptSetting('S_IsChannelServer', 'boolean', 'Set the server as a channel server', false),
            new ModeScriptSetting('S_AllowRespawn', 'boolean', 'Allow the players to respawn or not', true),
            new ModeScriptSetting('S_RespawnBehaviour', 'integer', 'This setting control the behavior of the respawn button. It overrides the respawn behavior set by the game mode script and the S_AllowRespawn setting. It can takes one of the following values: 0 -> use the game mode value , 1 -> normal (respawn when pressing the button), 2 -> do nothing, 3 -> give up before first checkpoint, respawn after, 4 -> always give up', 0),
            new ModeScriptSetting('S_HideOpponents', 'boolean', 'Do not display the opponents cars', false),
            new ModeScriptSetting('S_UseLegacyXmlRpcCallbacks', 'boolean', 'Turn on/off the legacy xmlrpc callbacks', true)
        ]);
    }

    /**
     * @return Collection
     */
    public static function matchMaking(): Collection
    {
        return collect([
            new ModeScriptSetting('S_MatchmakingAPIUrl', 'text', "URL of the matchmaking API. If you don't plan to use a custom matchmaking function leave this setting at its default value.", 'https://prod.live.maniaplanet.com/ingame/public/matchmaking'),
            new ModeScriptSetting('S_MatchmakingMatchServers', 'text', 'A comma separated list of match servers logins', ''),
            new ModeScriptSetting('S_MatchmakingMode', 'integer', 'This is the most important setting. It can take one of these five values : 0 -> matchmaking turned off, standard server; 1 -> matchmaking turned on, use this server as a lobby server; 2 -> matchmaking turned on, use this server as a match server; 3 -> matchmaking turned off, use this server as a universal lobby server; 4 -> matchmaking turned off, use this server as a universal match server.', 0),
            new ModeScriptSetting('S_MatchmakingRematchRatio', 'real', 'Set the minimum ratio of players that have to agree to play a rematch before launching one. The value range from 0.0 to 1.0. Any negative value turns off the rematch vote.', -1.0),
            new ModeScriptSetting('S_MatchmakingRematchNbMax', 'integer', 'Maxium number of consecutive rematch', 2),
            new ModeScriptSetting('S_MatchmakingVoteForMap', 'boolean', 'Allow players to vote for the next played map', false),
            new ModeScriptSetting('S_MatchmakingProgressive', 'boolean', 'Can start a match with less players than the required number', false),
            new ModeScriptSetting('S_MatchmakingWaitingTime', 'integer', 'Waiting time at the beginning of the matches', 20),
            new ModeScriptSetting('S_LobbyRoundPerMap', '', 'Nb of rounds per map in lobby mode', 60),
            new ModeScriptSetting('S_LobbyMatchmakerPerRound', 'integer', 'Set how many times the matchmaking function is called before ending the current round of King of the Lobby', 6),
            new ModeScriptSetting('S_LobbyMatchmakerWait', 'integer', 'Set the waiting time before calling the matchmaking function again', 2),
            new ModeScriptSetting('S_LobbyMatchmakerTime', 'integer', 'Duration of the matchmaking function. It allows the players to see who they will play their match with or cancel it if necessary.', 8),
            new ModeScriptSetting('S_LobbyInstagib', 'boolean', 'Use the Laser instead of the Rocket in the lobby.', false),
            new ModeScriptSetting('S_LobbyDisplayMasters', 'boolean', 'Display a list of Masters players in the lobby', true),
            new ModeScriptSetting('S_LobbyDisableUI', 'boolean', 'Disable lobby UI', false),
            new ModeScriptSetting('S_LobbyAggressiveTransfer', 'boolean', 'Enable or disable the aggressive transfert mechanism', true),
            new ModeScriptSetting('S_KickTimedOutPlayers', 'boolean', 'Kick timed out players', true),
            new ModeScriptSetting('S_MatchmakingErrorMessage', 'text', 'This message is displayed in the chat to inform the players that an error occured in the matchmaking system', '...'),
            new ModeScriptSetting('S_MatchmakingLogAPIError', 'boolean', "Log the API errors. You can activate it if something doesn't work and you have to investigate. Otherwise it's better to leave it turned off because this can quickly write huge log files.", false),
            new ModeScriptSetting('S_MatchmakingLogAPIDebug', 'boolean', "Log the API errors. You can activate it if something doesn't work and you have to investigate. Otherwise it's better to leave it turned off because this can quickly write huge log files.", false),
            new ModeScriptSetting('S_MatchmakingLogMiscDebug', 'boolean', "Log the API errors. You can activate it if something doesn't work and you have to investigate. Otherwise it's better to leave it turned off because this can quickly write huge log files.", false),
            new ModeScriptSetting('S_ProgressiveActivation_WaitingTime', 'integer', 'Average waiting time before progressive matchmaking activate', 180000),
            new ModeScriptSetting('S_ProgressiveActivation_PlayersNbRatio', 'integer', "Multiply the required players nb by this, if there's less player in the lobby activate progressive", 1),
        ]);
    }

    /**
     * @return Collection
     */
    public static function roundsBase(): Collection
    {
        return collect([
            new ModeScriptSetting('S_PointsLimit', 'integer', 'Points limit', 100),
            new ModeScriptSetting('S_FinishTimeout', 'integer', 'Finish timeout (-1 automatic based on author time)', -1),
            new ModeScriptSetting('S_UseAlternateRules', 'boolean', 'Use alternate rules', false),
            new ModeScriptSetting('S_ForceLapsNb', 'integer', 'Force number of laps (-1 to use the map default)', -1),
            new ModeScriptSetting('S_DisplayTimeDiff', 'boolean', 'Display time difference at checkpoint', false),
            new ModeScriptSetting('S_PointsRepartition', 'text', 'Comma separated points distribution. eg: "10,6,4,3,2,1"', '10,6,4,3,2,1'),
        ]);
    }

    /**
     * @return Collection
     */
    public static function chase(): Collection
    {
        return collect([
            new ModeScriptSetting('S_TimeLimit', 'integer', 'Time limit (0 to disable, -1 automatic based on author time)', 900),
            new ModeScriptSetting('S_MapPointsLimit', 'integer', 'Map points limit', 3),
            new ModeScriptSetting('S_RoundPointsLimit', 'integer', 'Round points limit (0 to disable, negative values automatic based on number of checkpoints)', -5),
            new ModeScriptSetting('S_RoundPointsGap', 'integer', 'The number of round points lead a team must have to win the round', 3),
            new ModeScriptSetting('S_GiveUpMax', 'integer', 'Maximum number of give up per team', 1),
            new ModeScriptSetting('S_MinPlayersNb', 'integer', 'Minimum number of players in a team', 3),
            new ModeScriptSetting('S_ForceLapsNb', 'integer', 'Number of Laps (-1 to use the map default, 0 to disable laps limit)', 10),
            new ModeScriptSetting('S_FinishTimeout', 'integer', 'Finish timeout (-1 automatic based on author time)', -1),
            new ModeScriptSetting('S_DisplayWarning', 'boolean', "Display a big red message in the middle of the screen of the player that crosses a checkpoint when it wasn't it's turn", true),
            new ModeScriptSetting('S_CompetitiveMode', 'boolean', 'Use competitive mode', false),
            new ModeScriptSetting('S_PauseBetweenRound', 'integer', 'Pause duration between rounds', 20),
            new ModeScriptSetting('S_WaitingTimeMax', 'integer', 'Maximum waiting time before next map', 600),
            new ModeScriptSetting('S_WaypointEventDelay', 'integer', 'Waypoint event buffer delay', 500),
            new ModeScriptSetting('S_WarmUpNb', 'integer', 'Number of warm up', 0),
            new ModeScriptSetting('S_WarmUpDuration', 'integer', 'Duration of one warm up', 0),
            new ModeScriptSetting('S_NbPlayersPerTeamMax', 'integer', 'Maximum number of players per team in matchmaking', 3),
            new ModeScriptSetting('S_NbPlayersPerTeamMin', 'integer', 'Minimum number of players per team in matchmaking', 3),
        ])
            ->merge(self::all())
            ->merge(self::matchMaking())
            ->keyBy([self::class, 'keyBy']);
    }

    /**
     * @return Collection
     */
    public static function chaseAttack(): Collection
    {
        return collect([
            new ModeScriptSetting('S_TimeLimit', 'integer', "Time limit (0 to disable, -1 automatic based on author time)", 300),
            new ModeScriptSetting('S_ForceLapsNb', 'integer', "Number of Laps (-1 to use the map default, 0 to disable laps limit)", -1),
            new ModeScriptSetting('S_FinishTimeout', 'integer', "Finish timeout (-1 automatic based on author time)", 5),
            new ModeScriptSetting('S_DisplayWarning', 'boolean', "Display a big red message in the middle of the screen of the player that crosses a checkpoint when it wasn't it's turn", true),
            new ModeScriptSetting('S_WaypointEventDelay', 'integer', "Waypoint event buffer delay", 300),
            new ModeScriptSetting('S_WarmUpNb', 'integer', "Number of warm up", 0),
            new ModeScriptSetting('S_WarmUpDuration', 'integer', "Duration of one warm up", 0),
        ])
            ->merge(self::all())
            ->keyBy([self::class, 'keyBy']);
    }

    /**
     * @return Collection
     */
    public static function cup(): Collection
    {
        return collect([
            new ModeScriptSetting('S_RoundsPerMap', 'integer', "Rounds per map", 5),
            new ModeScriptSetting('S_NbOfWinners', 'integer', "Number of winners before ending the match", 3),
            new ModeScriptSetting('S_WarmUpNb', 'integer', "Number of warm up", 0),
            new ModeScriptSetting('S_WarmUpDuration', 'integer', "Duration of one warm up", 0),
            new ModeScriptSetting('S_NbOfPlayersMax', 'integer', "Maximum number of players in matchmaking", 4),
            new ModeScriptSetting('S_NbOfPlayersMin', 'integer', "Minimum number of players in matchmaking", 4),
        ])
            ->merge(self::all())
            ->merge(self::roundsBase())
            ->merge(self::matchMaking())
            ->keyBy([self::class, 'keyBy']);
    }

    /**
     * @return Collection
     */
    public static function laps(): Collection
    {
        return collect([
            new ModeScriptSetting('S_TimeLimit', 'integer', "Time limit (0 to disable, -1 automatic based on author time)", 0),
            new ModeScriptSetting('S_ForceLapsNb', 'integer', "Number of Laps (-1 to use the map default)", 5),
            new ModeScriptSetting('S_FinishTimeout', 'integer', "Finish timeout (-1 automatic based on author time)", -1),
            new ModeScriptSetting('S_WarmUpNb', 'integer', "Number of warm up", 0),
            new ModeScriptSetting('S_WarmUpDuration', 'integer', "Duration of warm up", 0),
            new ModeScriptSetting('S_DisableGiveUp', 'boolean', "Prevent players from giving up the race", false),
        ])
            ->merge(self::all())
            ->keyBy([self::class, 'keyBy']);
    }

    /**
     * @return Collection
     */
    public static function rounds(): Collection
    {
        return collect([
            new ModeScriptSetting('S_PointsLimit', 'integer', "Points limit (negative value to disable)", 50),
            new ModeScriptSetting('S_RoundsPerMap', 'integer', "Number of round to play on one map before going to the next one (negative value to disable)", -1),
            new ModeScriptSetting('S_MapsPerMatch', 'integer', "Number of maps to play before finishing the match (negative value to disable)", -1),
            new ModeScriptSetting('S_UseTieBreak', 'boolean', "Continue to play the map until the tie is broken", true),
            new ModeScriptSetting('S_WarmUpNb', 'integer', "Number of warm up", 0),
            new ModeScriptSetting('S_WarmUpDuration', 'integer', "Duration of one warm up", 0),
        ])
            ->merge(self::all())
            ->merge(self::roundsBase())
            ->keyBy([self::class, 'keyBy']);
    }

    /**
     * @return Collection
     */
    public static function team(): Collection
    {
        return collect([
            new ModeScriptSetting('S_PointsLimit', 'integer', "Points limit", 5),
            new ModeScriptSetting('S_MaxPointsPerRound', 'integer', "The maximum number of points attributed to the first player to cross the finish line", 6),
            new ModeScriptSetting('S_PointsGap', 'integer', "The number of points lead a team must have to win the map", 1),
            new ModeScriptSetting('S_UseCustomPointsRepartition', 'boolean', "Use a custom points repartition defined with xmlrpc", false),
            new ModeScriptSetting('S_CumulatePoints', 'boolean', "At the end of the round both teams win their players points", false),
            new ModeScriptSetting('S_RoundsPerMap', 'integer', "Number of rounds to play on one map before going to the next one (0 or less to disable)", -1),
            new ModeScriptSetting('S_MapsPerMatch', 'integer', "Number of maps to play before finishing the match (0 or less to disable)", -1),
            new ModeScriptSetting('S_UseTieBreak', 'boolean', "Continue to play the map until the tie is broken", true),
            new ModeScriptSetting('S_WarmUpNb', 'integer', "Number of warm up", 0),
            new ModeScriptSetting('S_WarmUpDuration', 'integer', "Duration of one warm up", 0),
            new ModeScriptSetting('S_NbPlayersPerTeamMax	', 'integer', "Maximum number of players per team in matchmaking", 3),
            new ModeScriptSetting('S_NbPlayersPerTeamMin', 'integer', "Minimum number of players per team in matchmaking", 3),
        ])
            ->merge(self::all())
            ->merge(self::roundsBase())
            ->merge(self::matchMaking())
            ->keyBy([self::class, 'keyBy']);
    }

    /**
     * @return Collection
     */
    public static function timeAttack(): Collection
    {
        return collect([
            new ModeScriptSetting('S_TimeLimit', 'integer', "Time limit", 300),
            new ModeScriptSetting('S_WarmUpNb', 'integer', "Number of warm up", 0),
            new ModeScriptSetting('S_WarmUpDuration', 'integer', "Duration of one warm up", 0),
            new ModeScriptSetting('S_ForceLapsNb', 'integer', "Number of Laps (-1 to use the map default, 0 to disable laps limit)", 0),
        ])
            ->merge(self::all())
            ->keyBy([self::class, 'keyBy']);
    }

    /**
     * @param string $mode
     * @return Collection
     */
    public static function getSettingsByMode(string $mode)
    {
        switch ($mode) {
            case 'TimeAttack.Script.txt':
            case 'Trackmania/TM_TimeAttack_Online.Script.txt':
                return self::timeAttack();

            case 'Rounds.Script.txt':
            case 'Trackmania/TM_Rounds_Online.Script.txt':
                return self::rounds();

            case 'Laps.Script.txt':
            case 'Trackmania/TM_Laps_Online.Script.txt':
                return self::laps();

            case 'Teams.Script.txt':
            case 'Trackmania/TM_Teams_Online.Script.txt':
                return self::team();

            case 'Cup.Script.txt':
            case 'Trackmania/TM_Cup_Online.Script.txt':
                return self::cup();
        }

        return self::all();
    }

    /**
     * @param ModeScriptSetting $modeScriptSetting
     * @return string
     */
    public static function keyBy(ModeScriptSetting $modeScriptSetting)
    {
        return $modeScriptSetting->getSetting();
    }
}