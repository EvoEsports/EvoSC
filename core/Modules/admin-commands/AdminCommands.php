<?php


use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Template;
use esc\controllers\PlayerController;
use esc\models\Player;

class AdminCommands
{
    public function __construct()
    {
        Template::add('acp', File::get(__DIR__ . '/Templates/acp.latte.xml'));

        Hook::add('BeginMap', 'AdminCommands::showAdminControlPanel');
        Hook::add('PlayerConnect', 'AdminCommands::showAdminControlPanel');
    }

    public static function showAdminControlPanel(...$vars)
    {
        $admins = PlayerController::getPlayers()->filter(function (Player $ply) {
            return $ply->hasGroup(['Admin', 'SuperAdmin']);
        });

        foreach ($admins as $player) {
            Template::show($player, 'acp');
        }
    }
}