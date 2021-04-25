<?php


namespace EvoSC\Modules\Ranks;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class Ranks extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('/ranks', [self::class, 'showRanks'], 'Show an overview of the players server ranking.');

        ManiaLinkEvent::add('ranks.list', [self::class, 'showRanks']);
    }

    public static function showRanks(Player $player, $page = 0)
    {
        $page = intval($page);
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
            ->select(['players.NickName as name', 'players.Login as login', 'stats.Score as score', 'stats.Rank'])
            ->leftJoin('players', 'stats.Player', '=', 'players.id')
            ->whereBetween('stats.Rank', [$start + 1, $end])
            ->where('stats.Score', '>', 0)
            ->orderBy('stats.Rank')
            ->get()
            ->values()
            ->chunk($perPage / 3);

        $pageInfo = ($page + 1) . '/' . $maxPage;

        Template::show($player, 'Ranks.window', compact('ranks', 'pageInfo', 'page'));
    }
}