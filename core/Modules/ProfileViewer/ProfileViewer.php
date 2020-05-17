<?php

namespace EvoSC\Modules\ProfileViewer;


use EvoSC\Classes\DB;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class ProfileViewer extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ManiaLinkEvent::add('profile', [self::class, 'showProfile']);
    }

    /**
     * @param Player $player
     * @param string $targetLogin
     */
    public static function showProfile(Player $player, string $targetLogin)
    {
        $target = player($targetLogin);

        if ($target) {
            $values = collect([
                'Login' => $target->Login,
                'Nickname' => $target->NickName,
                'Location' => $target->path,
                'Group' => $target->group->Name,
                'Last seen' => $target->last_visit->diffForHumans(),
                'Server Rank' => ($target->stats->Rank ?? '?') . '.',
                'Server Score' => ($target->stats->Score ?? 0) . ' Points',
                'Locals' => DB::table(LocalRecords::TABLE)->where('Player', '=', $target->id)->count(),
                'Dedis' => DB::table(Dedimania::TABLE)->where('Player', '=', $target->id)->count(),
                'Maps' => DB::table('maps')->where('author', '=', $target->id)->count(),
                'Visits' => $target->stats->Visits ?? '0',
                'Playtime' => round(($target->stats->Playtime ?? 0) / 3600.0, 1) . 'h',
                'Finishes' => $target->stats->Finishes ?? '0',
                'Wins' => $target->stats->Wins ?? '0',
                'Donations' => ($target->stats->Donations ?? 0) . ' Planets',
            ]);

            $zonePath = $target->getOriginal('path');

            Template::show($player, 'ProfileViewer.window', compact('values', 'zonePath'));
        } else {
            warningMessage('Player with login ', secondary($targetLogin), ' not found.')->send($player);
        }
    }
}