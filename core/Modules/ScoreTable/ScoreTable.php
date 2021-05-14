<?php


namespace EvoSC\Modules\ScoreTable;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\PointsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\GroupManager\GroupManager;
use Illuminate\Support\Collection;

class ScoreTable extends Module implements ModuleInterface
{
    private static string $scoreboardTemplate;
    private static Collection $finalists;
    private static Collection $winners;
    private static string $firstFinishedLogin = '';
    private static int $nbOfWinners = 0;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet()) {
            self::$scoreboardTemplate = 'ScoreTable.scoreboard';
        } else {
            self::$scoreboardTemplate = 'ScoreTable.scoreboard_2020';
        }

        Hook::add('PlayerConnect', [self::class, 'sendScoreTable']);

        if (ModeController::cup()) {
            self::$winners = collect();
            self::$finalists = collect();
            Template::showAll('ScoreTable.update-winners', ['winners' => self::$winners]);
            Hook::add('Scores', [self::class, 'scores']);
            Hook::add('PlayerFinish', [self::class, 'decideWinner']);
            Hook::add('Maniaplanet.StartPlayLoop', [self::class, 'resetFirstFinished']);
            Hook::add('BeginMap', [self::class, 'resetWinners']);
            self::$nbOfWinners = (int)Server::getModeScriptSetting('S_NbOfWinners');
        }

        if (isTrackmania()) {
            Server::triggerModeScriptEvent('Common.UIModules.SetProperties', [json_encode([
                'uimodules' => [
                    [
                        'id' => 'Race_ScoresTable',
                        'visible' => false,
                        'visible_update' => true
                    ]
                ]
            ])]);
        }
    }

    /**
     *
     */
    public static function resetFirstFinished()
    {
        self::$firstFinishedLogin = '';
    }

    /**
     *
     */
    public static function resetWinners()
    {
        if (self::$winners->count() >= self::$nbOfWinners) {
            self::$winners = collect();
            self::$finalists = collect();
        }
    }

    /**
     * @param Player $player
     * @param int $score
     */
    public static function decideWinner(Player $player, int $score)
    {
        if ($score == 0 || self::$winners->count() >= self::$nbOfWinners || self::$firstFinishedLogin != '') {
            return;
        }

        self::$firstFinishedLogin = $player->Login;

        if (self::$finalists->has($player->Login)) {
            $place = self::$winners->count() + 1;
            self::$winners->put($player->Login, $place);
            self::$finalists->forget($player->Login);
            infoMessage($player, ' takes the ', secondary("$place. place"))->sendAll();
            Template::showAll('ScoreTable.update-winners', ['winners' => self::$winners]);
        }
    }

    /**
     * @param $scores
     */
    public static function scores($scores)
    {
        $finalists = collect($scores->players)
            ->where('matchpoints', '>=', PointsController::getCurrentPointsLimit());

        foreach ($finalists as $finalist) {
            if (!self::$finalists->has($finalist->login) && !self::$winners->has($finalist->login)) {
                self::$finalists->put($finalist->login, 1);
                infoMessage(secondary('You\'re a finalist now!'))->send($finalist->login);
            }
        }
    }

    public static function sendScoreTable(Player $player)
    {
        $logoUrl = config('scoretable.logo-url');
        $maxPlayers = Server::getMaxPlayers()['CurrentValue'];
        $roundsPerMap = Server::getModeScriptSetting('S_RoundsPerMap');

        $joinedPlayerInfo = collect([$player])->map(function (Player $player) {
            return [
                'login' => $player->Login,
                'name' => ml_escape($player->NickName),
                'groupId' => $player->group->id
            ];
        })->keyBy('login');

        $playerInfo = onlinePlayers()->map(function (Player $player) {
            return [
                'login' => $player->Login,
                'name' => ml_escape($player->NickName),
                'groupId' => $player->group->id
            ];
        })->keyBy('login');

        GroupManager::sendGroupsInformation($player);
        Template::showAll('ScoreTable.update', ['players' => $joinedPlayerInfo], 20);
        Template::show($player, 'ScoreTable.update', ['players' => $playerInfo], false, 20);
        Template::show($player, self::$scoreboardTemplate, compact('logoUrl', 'maxPlayers', 'roundsPerMap'));
    }
}