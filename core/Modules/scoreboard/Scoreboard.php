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

        foreach (onlinePlayers() as $player) {
            self::$playersOnline->put($player->id, true);
        }

        self::scoresUpdated(ScoreController::getTracker());

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

    public static function scoresUpdated(Collection $tracker)
    {
        self::$tracker = $tracker;
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
        Template::show($player, 'scoreboard.scoreboard', compact('logoUrl', 'maxPlayers', 'settings'));

        self::$playersOnline->put($player->id, true);
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
                'online' => $online
            ];

            if (self::$tracker->has($playerId)) {
                $data['score'] = self::$tracker->get($playerId)->best_score;
            }

            return $data;
        });

        $finished = $players->where('score', '>', 0)->sortBy('score');
        $noFinish = $players->where('score', '=', 0)->sortByDesc('online');
        $players = $finished->merge($noFinish)->values();

        Template::showAll('scoreboard.update-player-infos', compact('players'));
    }
}