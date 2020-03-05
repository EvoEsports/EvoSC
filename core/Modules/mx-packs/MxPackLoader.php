<?php


namespace esc\Modules;


use esc\Classes\Cache;
use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\MxPackJob;
use esc\Classes\RestClient;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MxPackLoader extends Module implements ModuleInterface
{
    /**
     * @var MxPackJob
     */
    private static MxPackJob $activeJob;

    public function __construct()
    {
        if (!is_dir(cacheDir('map-packs'))) {
            mkdir(cacheDir('map-packs'));
        }

        ChatCommand::add('//addpack', [self::class, 'showAddMapPack'],
            'Download a map pack from MX. First parameter is the pack-id and second (optional) is a password if it is protected.',
            'map_add');

        ManiaLinkEvent::add('mappack.aprove', [self::class, 'downloadMapPack'], 'map_add');
    }

    public static function showAddMapPack(Player $player, string $packId, string $secret = null)
    {
        $cacheIdInfo = 'map-packs/'.$packId.'_info';
        $cacheIdTracks = 'map-packs/'.$packId.'_trackslist';

        if (Cache::has($cacheIdInfo)) {
            $info = Cache::get($cacheIdInfo);
        } else {
            $url = sprintf('https://api.mania-exchange.com/tm/mappacks/%d/?=%s', $packId, $secret);

            $response = RestClient::get($url);
            $info = json_decode($response->getBody()->getContents());

            if ($response->getStatusCode() != 200 || !$info) {
                warningMessage('Failed to get information for map-pack ', secondary($packId))->send($player);

                return;
            }

            $info = $info[0];

            Cache::put($cacheIdInfo, $info, now()->addDay());
        }

        if (Cache::has($cacheIdTracks)) {
            $trackList = Cache::get($cacheIdTracks);
        } else {
            $url = sprintf('https://api.mania-exchange.com/tm/mappack/%d/tracks/?=%s', $packId, $secret);

            try {
                $response = RestClient::get($url);

                if ($response->getStatusCode() != 200) {
                    throw new Exception('Failed to get map-list.');
                }

                $trackList = json_decode($response->getBody()->getContents());

                Cache::put($cacheIdTracks, $trackList, now()->addDays(1));
            } catch (Exception $e) {
                warningMessage('Failed to get map-list from pack ', secondary($packId))->send($player);

                return;
            } catch (GuzzleException $e) {
                warningMessage('Failed to get map-list from pack ', secondary($packId))->send($player);

                return;
            }
        }

        Template::show($player, 'mx-packs.confirm', compact('trackList', 'info'));
    }

    public static function downloadMapPack(Player $player, $mapPackId)
    {
        if (isset(self::$activeJob)) {
            warningMessage('Can not download two map-packs at once, please wait.')->send($player);

            return;
        }

        self::$activeJob = new MxPackJob($player, $mapPackId);
        self::$activeJob = null;
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}