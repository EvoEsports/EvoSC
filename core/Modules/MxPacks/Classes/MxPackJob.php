<?php


namespace EvoSC\Modules\MxPacks\Classes;

use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\MatchSettingsController;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\MxPacks\MxPacks;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use EvoSC\Classes\File;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Exchange;
use EvoSC\Classes\Server;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use GuzzleHttp\Psr7;

class MxPackJob
{
    private int $id;
    private string $name;
    private string $path;
    private string $packsDir;
    private $info;
    private $tracks;
    private string $secret;

    /**
     * @var Player
     */
    private Player $issuer;

    public function __construct(Player $player, int $packId, string $secret)
    {
        infoMessage('Downloading map pack ', secondary($packId), ' from Exchange.')->sendAdmin();

        $this->packsDir = MapController::getMapsPath('MXPacks');

        if (!is_dir($this->packsDir)) {
            mkdir($this->packsDir);
        }

        $this->info = MxPacks::getPackInfo($packId, $secret);
        $this->tracks = MxPacks::getPackMapInfos($packId, $secret);
        $this->name = $this->info->ID;
        $this->path = "MXPacks" . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR;
        $this->issuer = $player;
        $this->id = $packId;
        $this->secret = $secret;

        try {
            $this->loadFiles();
        } catch (Exception $e) {
            Log::errorWithCause('Failed to download map pack', $e);
            warningMessage('Failed to download map pack: ', secondary($e->getMessage()))->send($player);
        }
    }

    private function updateStatus(array $info)
    {
        Template::show($this->issuer, 'MxPacks.update', [
            'info' => json_encode((object)$info)
        ]);
    }

    /**
     * @throws Exception
     */
    private function loadFiles()
    {
        $this->updateStatus([
            'packId'   => $this->id,
            'total'    => $this->info->TrackCount,
            'current'  => 0,
            'message'  => 'Downloading map pack...',
            'finished' => false
        ]);

        $this->downloadFiles();
    }

    private function downloadFiles() {
        $url = isManiaPlanet() ? Exchange::MANIAPLANET_MX_URL : Exchange::TRACKMANIA_MX_URL;
        $files = array();
        for($i = 0; $i < count($this->tracks); $i++) {
            $track = $this->tracks[$i];
            try {
                $download = RestClient::get($url . '/tracks/download/' . $track->TrackID);

                if ($download->getStatusCode() != 200) {
                    throw new Exception("Download $track->TrackID failed: " . $download->getReasonPhrase());
                }

                Log::write("Request $track->TrackID finished.", true);

                if ($download->getHeader('Content-Type')[0] != 'application/x-gbx') {
                    throw new Exception('File is not a valid GBX.');
                }
                $header_filename = Psr7\Header::parse($download->getHeader('content-disposition'));
                $header_filename = str_replace(" ", "_", $header_filename[0]['filename']);
                $filename = $this->path . $track->TrackID . "_$header_filename";
                Log::write('Saving map as ' . MapController::getMapsPath($filename), true);
                File::put(MapController::getMapsPath($filename), $download->getBody()->getContents());

                if (!File::exists(MapController::getMapsPath($filename))) {
                    throw new Exception('Map download failed, map does not exist.', true);
                }
                else {
                    $files[$i] = (object)array("filename"=>$filename,"mxid"=>$track->TrackID);
                }
            } catch (Exception $ex) {
                Log::errorWithCause($ex->getMessage(), $ex);
            } catch (GuzzleException $ex) {
                Log::errorWithCause($ex->getMessage(), $ex);
            }
        }
        $this->addFiles($files);
    }

    /**
     * @param array $files
     */
    private function addFiles(array $files)
    {
        $mapInfos = collect(MxPacks::getPackMapInfos($this->id, $this->secret))
            ->keyBy('GbxMapName');

        foreach ($files as $i => $value) {
            $name = basename($value->filename);
            $mx_id = $value->mxid;
            $filename = 'MXPacks' . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR . $name;

            $gbx = MapController::getGbxInformation($filename, false);
            $uid = $gbx->MapUid;
            $authorLogin = $gbx->AuthorLogin;

            $this->updateStatus([
                'packId'   => $this->id,
                'total'    => $this->info->TrackCount,
                'current'  => $i + 1,
                'message'  => 'Adding ' . stripAll($gbx->Name),
                'finished' => false
            ]);

            if (!Map::whereUid($uid)->exists()) {
                $exchangeMapInfo = $mapInfos->get($gbx->Name);
                $authorName = $exchangeMapInfo->Username ?? $gbx->AuthorLogin;

                if (Player::whereLogin($authorLogin)->exists()) {
                    $authorId = Player::find($authorLogin)->id;
                } else {
                    $authorId = Player::insertGetId([
                        'NickName' => $authorName,
                        'Login'    => $authorLogin
                    ]);
                }

                Map::create([
                    'uid'         => $uid,
                    'filename'    => $filename,
                    'folder'      => 'MXPacks'.DIRECTORY_SEPARATOR. $this->name,
                    'author'      => $authorId,
                    'mx_id'       => $mx_id,
                    'enabled'     => 1,
                    'cooldown'    => 999,
                    'name'        => $gbx->Name,
                    'environment' => $gbx->Environment,
                    'title_id'    => $gbx->TitleId
                ]);
            }

            $map = Map::whereUid($uid)->first();

            $map->update([
                'enabled' => 1
            ]);

            MatchSettingsController::addMapToCurrentMatchSettings($filename, $map->uid);

            try {
                Server::addMap($filename);
            } catch (Exception $e) {
                Log::errorWithCause("Failed to add map $filename", $e);
            }
        }

        Hook::fire('MapPoolUpdated');
        $url = isManiaPlanet() ? Exchange::MANIAPLANET_MX_URL : Exchange::TRACKMANIA_MX_URL;

        $this->updateStatus([
            'packId'   => $this->id,
            'total'    => $this->info->TrackCount,
            'current'  => $this->info->TrackCount,
            'message'  => 'Map pack added',
            'finished' => true
        ]);

        infoMessage($this->issuer, ' added map-pack ',
            '$l[' . $url . '/mappack/view/' . $this->id . ']' . secondary($this->info->Name),
            '$l from Exchange.')->sendAll();
    }
}
