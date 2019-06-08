<?php

namespace esc\Modules;


use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Player;

class BanGUI
{
    public function __construct()
    {
        //stuff
    }

    public static function showBanUi(Player $player)
    {
        $bans = Server::getBlackList(0, 999);
        var_dump($bans);

        Template::show($player, 'ban-gui.manialink', compact($bans));
    }
}