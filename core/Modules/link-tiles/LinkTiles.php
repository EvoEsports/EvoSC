<?php

namespace esc\Modules\LinkTiles;

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Player;
use Maniaplanet\DedicatedServer\InvalidArgumentException;

class LinkTiles
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [LinkTiles::class, 'playerConnect']);

        \esc\Classes\ManiaLinkEvent::add('openlink', [LinkTiles::class, 'openLink']);

        foreach (onlinePlayers() as $player) {
            LinkTiles::displayLinkTiles($player);
        }
    }

    public static function onConfigReload()
    {
        foreach (onlinePlayers() as $player) {
            LinkTiles::displayLinkTiles($player);
        }
    }

    public static function openLink(Player $player, $url)
    {
        try {
            Server::getRpc()->sendOpenLink($player->Login, $url, 0); // 1 = manialink browser, 0 = steam/external browser
        } catch (InvalidArgumentException $e) {
            Log::logAddLine('LinkTiles', "Failed to send url $url to player $player->Login: " . $e->getMessage());
        }
    }

    public static function playerConnect(Player $player)
    {
        self::displayLinkTiles($player);
    }

    public static function displayLinkTiles(Player $player)
    {
        $tiles = config('tiles.tiles');

        Template::show($player, 'link-tiles.linktiles', [
            'tiles' => $tiles
        ]);
    }
}