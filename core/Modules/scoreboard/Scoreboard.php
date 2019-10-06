<?php


namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\PlayerController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
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
    private static $scores;

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$logoUrl = config('scoreboard.logo-url');
        self::$tracker = collect();
        self::$scores = collect();

        onlinePlayers()->where('Score', '>', 0)->each(function (Player $player) {
            self::$scores->put($player->Login, $player->Score);
        });

        onlinePlayers()->each(function (Player $player) {
            self::$tracker->put($player->Login, [
                'login' => $player->Login,
                'name' => $player->NickName,
                'group_prefix' => $player->group->chat_prefix,
                'group_color' => $player->group->color,
                'group_name' => $player->group->Name,
                'score' => self::$scores->get($player->Login) ?? 0,
                'online' => true
            ]);
        });

//        Hook::add('BeginMap', [self::class, 'beginMap']);
//        Hook::add('PlayerConnect', [self::class, 'sendScoreboard']);
//        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect']);
//        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
    }

    public static function beginMap(Map $map)
    {
        self::$tracker = collect();
        self::$scores = collect();
        self::updatePlayerList();
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score == 0 || self::$scores->has($player->Login) && self::$scores->get($player->Login) <= $score) {
            return;
        }

        self::$scores->put($player->Login, $score);
        self::updatePlayerList();
    }

    public static function playerDisconnect(Player $player)
    {
        self::$tracker->transform(function ($tracker) use ($player) {
            if ($tracker['login'] == $player->Login) {
                $tracker['online'] = false;
            }
            return $tracker;
        });

        self::updatePlayerList();
    }

    public static function updatePlayerList()
    {
        $onlinePlayers = onlinePlayers();

        self::$tracker->transform(function ($tracker) use ($onlinePlayers) {
            if (!$onlinePlayers->contains('Login', $tracker['login'])) {
                $tracker['online'] = false;
            }

            $tracker['score'] = self::$scores->get($tracker['login']) ?? 0;

            return $tracker;
        });

        foreach ($onlinePlayers as $player) {
            if (!self::$tracker->has($player->Login)) {
                self::$tracker->put($player->Login, [
                    'login' => $player->Login,
                    'name' => $player->NickName,
                    'group_prefix' => $player->group->chat_prefix,
                    'group_color' => $player->group->color,
                    'group_name' => $player->group->Name,
                    'score' => self::$scores->get($player->Login) ?? 0,
                    'online' => true
                ]);
            }
        }

        $players = self::$tracker->where('score', '>', 0)->sortBy('score')->merge(self::$tracker->where('score', '=',
            0))->values();

        Template::showAll('scoreboard.update-player-infos', compact('players'));
    }

    public static function sendScoreboard(Player $player)
    {
        $logoUrl = self::$logoUrl;
        $maxPlayers = Server::getMaxPlayers()['CurrentValue'];
        self::updatePlayerList();
        Template::show($player, 'scoreboard.scoreboard', compact('logoUrl', 'maxPlayers'));
    }
}