<?php


namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ScoreController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;
use Illuminate\Support\Collection;

class Scoreboard implements ModuleInterface
{
    private static $logoUrl;

    /**
     * @var Collection
     */
    private static $tracker;

    /**
     * @var Collection
     */
    private static $playersOnline;

    private static $mode;

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$logoUrl = config('scoreboard.logo-url');
        self::$playersOnline = collect();
        self::$mode = $mode;

        foreach (onlinePlayers() as $player) {
            self::$playersOnline->put($player->id, true);
        }

        self::scoresUpdated(ScoreController::getTracker());

        if (!$isBoot) {
            $logoUrl = self::$logoUrl;
            $maxPlayers = Server::getMaxPlayers()['CurrentValue'];
            $pointLimitRounds = Server::getRoundPointsLimit()["CurrentValue"];
            $mode = self::$mode;

            foreach (onlinePlayers() as $player) {
                $settings = $player->setting('sb');
                Template::show($player, 'scoreboard.scoreboard',
                    compact('logoUrl', 'maxPlayers', 'settings', 'pointLimitRounds', 'mode'));
            }
        }

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect']);
        Hook::add('ScoresUpdated', [self::class, 'scoresUpdated']);
        Hook::add('BeginMatch', [self::class, 'beginMatch']);
    }

    public static function beginMatch()
    {
        self::$tracker = collect();
        $playersOnline = collect();
        foreach (onlinePlayers() as $player) {
            $playersOnline->put($player->id, true);
        }
        self::$playersOnline = $playersOnline;
        self::updatePlayerList();
    }

    public static function playerDisconnect(Player $player)
    {
        self::$playersOnline->put($player->id, false);
        self::updatePlayerList();
    }

    public static function playerConnect(Player $player)
    {
        $logoUrl = self::$logoUrl;
        $maxPlayers = Server::getMaxPlayers()['CurrentValue'];
        $settings = $player->setting('sb');
        var_dump(Server::getRoundPointsLimit());
        $pointLimitRounds = Server::getRoundPointsLimit()["CurrentValue"];
        $mode = self::$mode;
        Template::show($player, 'scoreboard.scoreboard',
            compact('logoUrl', 'maxPlayers', 'settings', 'pointLimitRounds', 'mode'));

        self::$playersOnline->put($player->id, true);
        self::updatePlayerList();
    }

    public static function scoresUpdated(Collection $tracker)
    {
        self::$tracker = $tracker;
        self::updatePlayerList();
    }

    public static function updatePlayerList()
    {
        $onlinePlayers = onlinePlayers()->keyBy('id');
        $players = self::$playersOnline->map(function ($online, $playerId) use ($onlinePlayers) {
            $player = $onlinePlayers->get($playerId);

            if (!$player) {
                $player = Player::whereId($playerId)->first();
            }

            $data = [
                'login' => $player->Login,
                'name' => $player->NickName,
                'group_prefix' => $player->group->chat_prefix,
                'group_color' => $player->group->color,
                'group_name' => $player->group->Name,
                'score' => 0,
                'last_points_received' => 0,
                'points' => 0,
                'online' => $online
            ];

            if (self::$tracker->has($playerId)) {
                $data['score'] = self::$tracker->get($playerId)->best_score;
                $data['points'] = self::$tracker->get($playerId)->points;
                $data['last_points_received'] = self::$tracker->get($playerId)->last_points_received;
            }

            return $data;
        });

        if (self::$mode == 'Rounds.Script.txt') {
            $finished = $players->where('score', '>', 0)->sortByDesc('points');
            $noFinish = $players->where('score', '=', 0)->sortByDesc('online');
        } else {
            $finished = $players->where('score', '>', 0)->sortBy('score');
            $noFinish = $players->where('score', '=', 0)->sortByDesc('online');
        }
        $players = $finished->merge($noFinish)->values();

        Template::showAll('scoreboard.update-player-infos', compact('players'));
    }
}