<?php

namespace EvoSC\Modules\LastVisits;

use Carbon\Carbon;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;


class LastVisits extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('/lastvisits', [self::class, 'showLastVisits'], 'Shows the lately connected players')->addAlias('/lv');

        ManiaLinkEvent::add('lastvisits.list', [self::class, 'showLastVisits']);
    }

    /**
     *
     *
     *
     */
    public static function showLastVisits(Player $player, $page = 0)
    {
      $page = intval($page);
      $perPage = 66;
      $total = DB::table('players')->count();
      $maxPage = ceil($total / $perPage);

      if ($page < 0) {
          $page = $maxPage - 1;
      } else if ($page >= $maxPage) {
          $page = 0;
      }

      $start = $page * $perPage;
      $end = $start + $perPage;

      $lastVisits = DB::table('players')
          ->select(['NickName as name', 'Login as login', 'last_visit as lastvisit'])
          ->where('last_visit', '>', 0)
          ->orderBy('last_visit', 'desc')
          ->offset($page * $perPage)
          ->limit($perPage)
          ->get()
          ->values()
          ->map(function($item)
          {
            $item->lastvisit = Carbon::parse($item->lastvisit)->diffForHumans();
            return $item;
          })
          ->chunk($perPage / 3);

      $pageInfo = ($page + 1) . '/' . $maxPage;

      Template::show($player, 'LastVisits.window', compact('lastVisits', 'pageInfo', 'page'));
    }
}
