<?php


namespace esc\Classes;


use esc\Controllers\MapController;

class MxMap
{
    public $directory;
    public $filename;
    public $gbxString;
    public $gbx;
    public $uid;
    public $mxId;
    public $mxDetails;

    /**
     * Move the map-file to a different directory.
     *
     * @param string $targetDirectory
     *
     * @throws \Exception
     */
    public function moveTo(string $targetDirectory)
    {
        if (substr($targetDirectory, -1) != '/') {
            $targetDirectory .= '/';
        }

        $mapFolder = MapController::getMapsPath();
        File::rename($mapFolder . $this->getFilename(), $mapFolder . $targetDirectory . $this->filename);

        if (!File::exists($mapFolder . $targetDirectory . $this->filename)) {
            throw new \Exception('Moving map "' . $this->getFilename() . '" to "' . $targetDirectory . $this->filename . '" failed.');
        }

        $this->directory = $targetDirectory;
    }

    /**
     * @throws \Exception
     */
    public function loadGbxInformationAndSetUid()
    {
        $this->gbxString = MapController::getGbxInformation($this->directory . $this->filename);
        $this->gbx       = json_decode($this->gbxString);

        if (!$this->gbx || !isset($this->gbx->MapUid)) {
            throw new \Exception('Failed to load GBX information of file "' . $this->directory . $this->filename . '".');
        }

        $this->uid = $this->gbx->MapUid;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function loadMxDetails()
    {
        $infoResponse = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $this->mxId);

        if ($infoResponse->getStatusCode() != 200) {
            throw new \Exception('Failed to get mx-details: ' . $infoResponse->getReasonPhrase());
        }

        $detailsBody = $infoResponse->getBody()->getContents();
        $info        = json_decode($detailsBody);

        if (!$info || isset($info->StatusCode)) {
            throw new \Exception('Failed to parse mx-details: ' . $detailsBody);
        }

        $this->mxDetails = $info[0];
    }

    /**
     * Gets the filename with directory.
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->directory . $this->filename;
    }

    /**
     * Delete the downloaded file.
     */
    public function delete()
    {
        File::delete(MapController::getMapsPath($this->getFilename()));
    }

    /**
     * Download map by mx-id and create MxMap object.
     *
     * @param $mxId
     *
     * @return \esc\Classes\MxMap
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public static function get($mxId): MxMap
    {
        if (is_string($mxId)) {
            $mxId = intval($mxId);
        }

        if (!$mxId || $mxId == 0) {
            throw new \Exception("Requested map with invalid id: $mxId");
        }

        $download = RestClient::get('http://tm.mania-exchange.com/tracks/download/' . $mxId);

        if ($download->getStatusCode() != 200) {
            throw new \Exception("Download $mxId failed: " . $download->getReasonPhrase());
        }

        Log::logAddLine('MxDownload', "Request $mxId finished.", isVeryVerbose());

        if ($download->getHeader('Content-Type')[0] != 'application/x-gbx') {
            throw new \Exception('File is not a valid GBX.');
        }

        $filename = preg_replace('/^attachment; filename="(.+)"$/', '\1', $download->getHeader('content-disposition')[0]);
        $filename = html_entity_decode(trim($filename), ENT_QUOTES | ENT_HTML5);
        $filename = preg_replace('/[^a-z0-9\-\_\#\ \.]/i', '', $filename);
        $filename = preg_replace('/\ /i', '_', $filename);

        File::put(MapController::getMapsPath($filename), $download->getBody());

        $mxMap            = new MxMap();
        $mxMap->filename  = $filename;
        $mxMap->directory = '';
        $mxMap->mxId      = $mxId;

        return $mxMap;
    }
}