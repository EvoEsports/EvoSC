<?php


namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class Ranks extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('/ranks', [self::class, 'showRanks']);

        ManiaLinkEvent::add('ranks.list', [self::class, 'showRanks']);
    }

    public static function showRanks(Player $player, $page = 0)
    {
        $perPage = 66;
        $total = DB::table('stats')->where('Score', '>', 0)->where('Rank', '>', 0)->count();
        $maxPage = ceil($total / $perPage);

        if ($page < 0) {
            $page = $maxPage - 1;
        } else if ($page >= $maxPage) {
            $page = 0;
        }

        $start = $page * $perPage;
        $end = $start + $perPage;

        $ranks = DB::table('stats')
            ->select(['players.NickName as name', 'players.Login as login', 'stats.Score as score', 'stats.Rank as rank'])
            ->leftJoin('players', 'stats.Player', '=', 'players.id')
            ->whereBetween('stats.Rank', [$start + 1, $end])
            ->where('stats.Score', '>', 0)
            ->orderBy('stats.Rank')
            ->get()
            ->values()
            ->chunk($perPage / 3);

        $pageInfo = ($page + 1) . '/' . $maxPage;

        dump($ranks);

        Template::show($player, 'ranks.window', compact('ranks', 'pageInfo', 'page'));
    }
}