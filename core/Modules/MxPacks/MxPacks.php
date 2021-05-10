<?php


namespace EvoSC\Modules\MxPacks;


use EvoSC\Classes\Cache;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Exchange;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\MxPacks\Classes\MxPackJob;
use Exception;
use EvoSC\Classes\Log;

class MxPacks extends Module implements ModuleInterface
{
    private static string $apiUrl;
    private static string $exchangeUrl;

    /**
     * @var MxPackJob
     */
    private static ?MxPackJob $activeJob;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet()) {
            self::$apiUrl = Exchange::MANIAPLANET_MX_API_URL;
            self::$exchangeUrl = Exchange::MANIAPLANET_MX_URL;
        } else {
            self::$apiUrl = Exchange::TRACKMANIA_MX_API_URL;
            self::$exchangeUrl = Exchange::TRACKMANIA_MX_URL;
        }

        if (!is_dir(cacheDir('map-packs'))) {
            mkdir(cacheDir('map-packs'));
        }

        ChatCommand::add('//addpack', [self::class, 'showAddMapPack'],
            'Download a map pack from MX. First parameter is the pack-id and second (optional) is a password if it is protected.',
            'map_add');

        ManiaLinkEvent::add('mappack.aprove', [self::class, 'downloadMapPack'], 'map_add');
    }

    public static function showAddMapPack(Player $player, $cmd, $packId, $secret = '')
    {
        $info = self::getPackInfo($packId, $secret);

        if (isset($info->Message)) {
            warningMessage($info->Message)->send($player);
            return;
        }

        $trackList = self::getPackMapInfos($packId, $secret);

        Template::show($player, 'MxPacks.confirm', compact('trackList', 'info', 'secret'));
    }

    public static function getPackMapInfos($packId, $secret)
    {
        $cacheKey = 'map-packs/' . $packId . '_trackslist';

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (isManiaPlanet()) {
            $url = sprintf(self::$apiUrl . '/tm/mappack/%d/tracks/?=%s', $packId, $secret);
        } else {
            $url = sprintf(self::$exchangeUrl . '/api/mappack/get_mappack_tracks/%d/?secret=%s', $packId, $secret);
        }

        //addpack 524 G5Gpl77r4B
        dump($url);

        try {
            $response = RestClient::get($url);

            if ($response->getStatusCode() != 200) {
                Log::error('Failed to get map list for map-pack', $response->getReasonPhrase());
                return null;
            }

            $trackList = json_decode($response->getBody()->getContents());
            Cache::put($cacheKey, $trackList, now()->addMinute());

            return $trackList;
        } catch (Exception $e) {
            Log::errorWithCause('Failed to get map list for map-pack', $e);
        }

        return null;
    }

    public static function getPackInfo($packId, $secret)
    {
        $cacheIdInfo = 'map-packs/' . $packId . '_info';
        if (Cache::has($cacheIdInfo)) {
            return Cache::get($cacheIdInfo);
        }

        if (isManiaPlanet()) {
            $url = sprintf(self::$apiUrl . '/tm/mappacks/%d/?=%s', $packId, $secret);
        } else {
            $url = sprintf(self::$apiUrl . '/api/mappack/get_info/%d/?secret=%s', $packId, $secret);
        }

        $response = RestClient::get($url);
        $info = json_decode($response->getBody()->getContents());

        if ($response->getStatusCode() != 200 || !$info) {
            Log::error('Failed to get information for map-pack', $response->getReasonPhrase());

            return null;
        }

        $info = isManiaPlanet() ? $info[0] : $info;
        Cache::put($cacheIdInfo, $info, now()->addMinute());

        return $info;
    }

    public static function downloadMapPack(Player $player, $mapPackId, $secret)
    {
        if (isset(self::$activeJob)) {
            warningMessage('Can not download two map-packs at once, please wait.')->send($player);
            return;
        }

        $info = self::getPackInfo($mapPackId, $secret);
        if ($info->TrackCount == 0) {
            warningMessage('Can not add empty map pack.')->send($player);
            return;
        }

        self::$activeJob = new MxPackJob($player, $mapPackId, $secret);
        self::$activeJob = null;
    }
}
