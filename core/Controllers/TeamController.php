<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use Maniaplanet\DedicatedServer\Structures\Tag;

class TeamController implements ControllerInterface
{
    public static function init()
    {
    }

    public static function start(string $mode, bool $isBoot)
    {
    }

    public static function getClubLinkUrl($name, $primaryColor = '000', $secondaryColor = '000'): string
    {
        return sprintf('https://club-link.evotm.workers.dev/?name=%s&primary=%s&secondary=%s', urlencode($name), $primaryColor, $secondaryColor);
    }
}