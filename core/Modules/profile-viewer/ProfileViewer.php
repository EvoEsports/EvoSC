<?php

namespace esc\Modules;


use esc\Classes\DB;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class ProfileViewer implements ModuleInterface
{
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

            Template::show($player, 'profile-viewer.window', compact('values', 'zonePath'));
        } else {
            warningMessage('Player with login ', secondary($targetLogin), ' not found.')->send($player);
        }
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ManiaLinkEvent::add('profile', [self::class, 'showProfile']);
    }
}