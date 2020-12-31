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

        self::resetWinnersAndFinalists();

        Hook::add('PlayerConnect', [self::class, 'sendScoreTable']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMatch', [self::class, 'resetWinnersAndFinalists']);

        if (ModeController::cup()) {
            Hook::add('Scores', [self::class, 'scores']);
        }
    }

    public static function resetWinnersAndFinalists()
    {
        self::$winners = collect();
        self::$finalists = collect();
        Template::showAll('ScoreTable.update-winners', ['winners' => self::$winners]);
    }

    public static function playerFinish(Player $player, int $score)
    {
        if ($score == 0 || self::$winners->count() >= Server::getModeScriptVariable('S_NbOfWinners')) {
            return;
        }

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
        $roundsPerMap = Server::getModeScriptVariable('S_RoundsPerMap');

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