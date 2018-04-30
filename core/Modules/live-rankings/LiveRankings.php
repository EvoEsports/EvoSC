<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class LiveRankings
{
    public function __construct()
    {
        Hook::add('PlayerConnect', 'LiveRankings::playerConnect');
        Hook::add('PlayerFinish', 'LiveRankings::playerFinish');
    }

    public static function show(Player $player)
    {
        //Get online players or players that finished (also display disconnected and finished players)
        $players = Player::whereOnline(true)->orWhere('Score', '>', 0)->get();

        $hideScript = Template::toString('scripts.hide', ['hideSpeed' => $player->user_settings->ui->hideSpeed ?? null, 'config' => config('ui.playerlist')]);

        Template::show($player, 'ranking-box', [
            'id' => 'live-rankings',
            'title' => '🏆 LIVE RANKINGS',
            'config' => config('ui.playerlist'),
            'hideScript' => $hideScript,
            'rows' => config('ui.playerlist.rows'),
            'scale' => config('ui.playerlist.scale'),
            'content' => Template::toString('live-rankings.playerlist', compact('players'))
        ]);
    }

    public static function playerFinish(Player $player, $score)
    {
        if ($score > 0) {
            //Update playerlist
            onlinePlayers()->each([self::class, 'show']);
        }
    }

    public static function playerConnect(Player $player)
    {
        onlinePlayers()->each([self::class, 'show']);
    }
}