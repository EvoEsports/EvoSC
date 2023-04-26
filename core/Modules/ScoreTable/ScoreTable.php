<?php


namespace EvoSC\Modules\ScoreTable;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\PointsController;
use EvoSC\Controllers\TemplateController;
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
    private static string $mode;
    private static Collection $layouts;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$mode = $mode;

        $defaultLayouts = [
            (object)[
                'default' => true,
                'mode'    => null,
                'file'    => __DIR__ . '/TableLayouts/default.xml',
                'id'      => "ScoreTable.Layouts.Default"
            ],
            (object)[
                'default' => false,
                'mode'    => 'Trackmania/TM_Teams_Online.Script.txt',
                'file'    => __DIR__ . '/TableLayouts/teams.xml',
                'id'      => "ScoreTable.Layouts.Teams"
            ],
            (object)[
                'default' => false,
                'mode'    => 'Trackmania/TM_TMWTTeams_Online.Script.txt',
                'file'    => __DIR__ . '/TableLayouts/teams.xml',
                'id'      => "ScoreTable.Layouts.Teams"
            ]
        ];

        foreach ($defaultLayouts as $layout) {
            self::addLayout($layout->mode, $layout->file, $layout->default);
        }

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
            if (config('scoretable.force-hide-default', true)) {
                Server::triggerModeScriptEvent('Common.UIModules.SetProperties', [json_encode([
                    'uimodules' => [
                        [
                            'id'             => 'Race_ScoresTable',
                            'visible'        => false,
                            'visible_update' => true
                        ]
                    ]
                ])]);
            }

            ScoreTable::sendScoreTable(null, $mode);
            Hook::add('PlayerChangedName', [self::class, 'playerChangedName']);
            Hook::add('GroupChanged', [self::class, 'playerChangedName']);
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

    /**
     * @param Player|null $player
     * @param string $mode
     * @return void
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendScoreTable(Player $player = null, string $mode = '')
    {
        $logoUrl = config('scoretable.logo-url');
        $maxPlayers = Server::getMaxPlayers()['CurrentValue'];
        $roundsPerMap = Server::getModeScriptSetting('S_RoundsPerMap', 0);

        if (empty($mode)) {
            $layoutId = self::getLayoutIdByModeName(ModeController::getMode());
        } else {
            $layoutId = self::getLayoutIdByModeName($mode);
        }

        $playerInfo = onlinePlayers()->map(function (Player $player) {
            return [
                'login'   => $player->Login,
                'name'    => ml_escape($player->NickName),
                'groupId' => $player->group->id
            ];
        })->keyBy('login');

        if ($player) {
            $joinedPlayerInfo = collect([$player])->map(function (Player $player) {
                return [
                    'login'   => $player->Login,
                    'name'    => ml_escape($player->NickName),
                    'groupId' => $player->group->id
                ];
            })->keyBy('login');

            GroupManager::sendGroupsInformation($player);
            Template::showAll('ScoreTable.update', ['players' => $joinedPlayerInfo], 20);
            Template::show($player, 'ScoreTable.update', ['players' => $playerInfo], false, 20);
            Template::show($player, self::$scoreboardTemplate, compact('logoUrl', 'maxPlayers', 'roundsPerMap', 'layoutId'));
        } else {
            GroupManager::sendGroupsInformation();
            Template::showAll('ScoreTable.update', ['players' => $playerInfo], 20);
            Template::showAll(self::$scoreboardTemplate, compact('logoUrl', 'maxPlayers', 'roundsPerMap', 'layoutId'));
        }
    }

    /**
     * @param Player|null $player
     * @return void
     */
    public static function playerChangedName(Player $player)
    {
        $playerInfo = collect([$player])->map(function (Player $player) {
            return [
                'login'   => $player->Login,
                'name'    => ml_escape($player->NickName),
                'groupId' => $player->group->id
            ];
        })->keyBy('login');

        Template::showAll('ScoreTable.update', ['players' => $playerInfo], 60);
    }

    /**
     * @param string|null $forMode
     * @param string $file
     * @param bool $isDefaultLayout
     * @return void
     */
    public static function addLayout(string $forMode = null, string $file, bool $isDefaultLayout = false)
    {
        if (!isset(self::$layouts)) {
            self::$layouts = collect();
        }

        self::$layouts->push((object)[
            'type'    => $forMode,
            'file'    => $file,
            'default' => $isDefaultLayout
        ]);

        TemplateController::overrideTemplate(sha1($file), $file);
    }

    /**
     * @param string $modeString
     * @return string
     */
    private static function getLayoutIdByModeName(string $modeString)
    {
        $layout = self::$layouts->firstWhere('type', '=', $modeString);

        if (is_null($layout)) {
            $layout = self::$layouts->firstWhere('default', '=', true);
        }

        return sha1($layout->file);
    }

    /**
     * @return Collection
     */
    public function getLayouts(): Collection
    {
        return self::$layouts;
    }
}