<?php

namespace esc\Modules;


use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
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
            Template::show($player, 'profile-viewer.window', compact('target'));
        }
    }
}