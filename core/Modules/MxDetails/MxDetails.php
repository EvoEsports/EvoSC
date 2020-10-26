<?php

namespace EvoSC\Modules\MxDetails;


use EvoSC\Classes\Cache;
use EvoSC\Classes\DB;
use EvoSC\Classes\Exchange;
use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\MxDownload\MxDownload;
use stdClass;

class MxDetails extends Module implements ModuleInterface
{
    private static ?string $apiUrl = null;  // prevents the "typed static property must not be accessed before initialization" error on Windows
    private static ?string $exchangeUrl = null;

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

        if (!File::dirExists(cacheDir('mx-details'))) {
            File::makeDir(cacheDir('mx-details'));
        }
        if (!File::dirExists(cacheDir('mx-wr'))) {
            File::makeDir(cacheDir('mx-wr'));
        }

        ManiaLinkEvent::add('mx.details', [self::class, 'showDetails']);
    }

    /**
     * @param Player $player
     * @param $mapIdOrUid
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showDetails(Player $player, $mapIdOrUid)
    {
        if (empty($mapIdOrUid)) {
            return;
        }

        $map = null;
        if (preg_match('/^\d+$/', $mapIdOrUid) > 0) {
            $map = Map::whereId($mapIdOrUid)->first();
        } else {
            $map = Map::whereUid($mapIdOrUid)->first();
        }

        if (is_null($map)) {
            warningMessage('Unknown map.')->send($player);
            return;
        }

        if (!$map->mx_details) {
            MxDownload::loadMxDetails($map);
        }

        if (!$map->mx_world_record && isManiaPlanet()) {
            self::loadMxWordlRecord($map);
        }

        $rating = -1;
        $totalVotes = 0;

        if (DB::table('mx-karma')->where('Map', '=', $map->id)->exists()) {
            $data = DB::table('mx-karma')
                ->selectRaw('AVG(Rating) as rating_avg, COUNT(Rating) AS total_votes')
                ->where('Map', '=', $map->id)
                ->first();

            $rating = $data->rating_avg;
            $totalVotes = $data->total_votes;
        }

        Template::show($player, 'MxDetails.window', compact('map', 'rating', 'totalVotes'));
    }

    /**
     * @param Map $map
     * @return stdClass|null
     */
    public static function loadMxWordlRecord(Map $map)
    {
        if (!$map->mx_id) {
            return null;
        }

        $result = RestClient::get(self::$apiUrl . '/tm/tracks/worldrecord/' . $map->mx_details->TrackID, ['timeout' => 0.75]);

        if ($result->getStatusCode() != 200) {
            Log::write('Failed to fetch MX world record: ' . $result->getReasonPhrase());

            return null;
        }

        $data = json_decode($result->getBody()->getContents());
        Cache::put('mx-wr/' . $map->mx_id, $data);

        return $data;
    }
}