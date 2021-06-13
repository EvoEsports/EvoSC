<?php


namespace EvoSC\Modules\MxPacks\Classes;

use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\MatchSettingsController;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\MxPacks\MxPacks;
use Exception;
use Illuminate\Support\Collection;
use ZipArchive;
use EvoSC\Classes\File;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Exchange;
use EvoSC\Classes\Server;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;

class MxPackJob
{
    private int $id;
    private string $name;
    private string $path;
    private string $packsDir;
    private $info;
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
        $this->name = $this->info->ID . '_' . $this->info->ShortName;
        $this->path = $this->packsDir . '/' . $this->name . '.zip';
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

        if (File::exists($this->path)) {
            $this->unpackFiles($this->path);
            return;
        }

        if (isManiaPlanet()) {
            $url = Exchange::MANIAPLANET_MX_URL;
        } else {
            $url = Exchange::TRACKMANIA_MX_API_URL;
        }

        $url = sprintf($url . '/mappack/download/%s?secret=%s', $this->info->ID, $this->secret);
        $response = RestClient::get($url);

        if ($response->getStatusCode() != 200) {
            warningMessage('Failed to download map pack ', secondary($this->info->Name))->send($this->issuer);

            return;
        }

        File::put($this->path, $response->getBody()->getContents());

        $this->unpackFiles($this->path);
    }

    /**
     * @param string $path
     * @throws Exception
     */
    private function unpackFiles(string $path)
    {
        $this->updateStatus([
            'packId'   => $this->id,
            'total'    => $this->info->TrackCount,
            'current'  => 0,
            'message'  => 'Extracting maps from archive...',
            'finished' => false
        ]);

        $zip = new ZipArchive();
        $dir = $this->packsDir . DIRECTORY_SEPARATOR . $this->name;

        if (is_dir($dir)) {
            File::delete($dir);
        }

        mkdir($dir);

        if ($zip->open($path) === true) {
            $zip->extractTo($dir);
            $zip->close();
        } else {
            throw new Exception('Failed to unzip archive.');
        }

        $this->addFiles(File::getFiles($dir));
    }

    /**
     * @param Collection $files
     * @throws Exception
     */
    private function addFiles(Collection $files)
    {
        $mapInfos = collect(MxPacks::getPackMapInfos($this->id, $this->secret))
            ->keyBy('GbxMapName');

        foreach ($files->values() as $i => $value) {
            $name = basename($value);
            $pattern = '/\((\d+)\)\.Map.Gbx$/';
            if (isManiaPlanet()) {
                $pattern = '/\((\d+)\)\.Gbx$/';
            }

            preg_match($pattern, $name, $matches);
            $mx_id = $matches[1];
            $filename = 'MXPacks'. DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR . $name;

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
