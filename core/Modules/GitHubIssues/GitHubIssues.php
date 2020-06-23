<?php


namespace EvoSC\Modules\GitHubIssues;


use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class GitHubIssues extends Module implements ModuleInterface
{
    public static function start(string $mode, bool $isBoot = false)
    {
    }

    public static function showIssues(Player $player)
    {
        //https://api.github.com/repos/EvoTM/EvoSC/issues
        Template::show($player, 'GitHubIssues.window');
    }
}