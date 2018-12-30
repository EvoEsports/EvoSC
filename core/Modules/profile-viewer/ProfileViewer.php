<?php

namespace esc\Modules;


use esc\Classes\ManiaLinkEvent;
use esc\Classes\MXK;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\Player;

class ProfileViewer
{
    public function __construct()
    {
        ManiaLinkEvent::add('profile', [self::class, 'showProfile']);
    }

    public static function showProfile(Player $player, string $targetLogin)
    {
        $target = Player::whereLogin($targetLogin)->first();

        if ($target) {
            $values = collect([
                'Login'        => $target->Login,
                'Nickname'     => $target->NickName,
                'Location'     => $target->path,
                'Group'        => $target->group->Name,
                'Last seen'    => $target->last_visit->diffForHumans(),
                'Server Rank'  => $target->stats->Rank . '.',
                'Server Score' => $target->stats->Score . ' Points',
                'Locals'       => $target->locals()->count(),
                'Dedis'        => $target->dedis()->count(),
                'Maps'         => Map::whereAuthor($target->id)->count(),
                'Visits'       => $target->stats->Visits,
                'Playtime'     => round($target->stats->Playtime / 60, 0) . 'h',
                'Finishes'     => $target->stats->Finishes,
                'Wins'         => $target->stats->Wins,
                'Donations'    => $target->stats->Donations . ' Planets',
            ]);

            $zonePath = $target->getOriginal('path');

            Template::show($player, 'profile-viewer.window', compact('values', 'zonePath'));
        } else {
            ChatController::message($player, '_warning', 'Player with login ', secondary($targetLogin), ' not found.');
        }
    }
}