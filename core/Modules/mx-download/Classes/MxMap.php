<?php


namespace esc\Classes;


use esc\Controllers\MapController;
use esc\Modules\MxDownload;

class MxMap implements \Serializable
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
     * @param  string  $targetDirectory
     *
     * @throws \Exception
     */
    public function moveTo(string $targetDirectory)
    {
        if (substr($targetDirectory, -1) != '/') {
            $targetDirectory .= '/';
        }

        $mapFolder = MapController::getMapsPath();
        File::rename($mapFolder.$this->getFilename(), $mapFolder.$targetDirectory.$this->filename);

        if (!File::exists($mapFolder.$targetDirectory.$this->filename)) {
            throw new \Exception('Moving map "'.$this->getFilename().'" to "'.$targetDirectory.$this->filename.'" failed.');
        }

        $this->directory = $targetDirectory;
    }

    /**
     * @throws \Exception
     */
    public function loadGbxInformationAndSetUid()
    {
        $this->gbx = MapController::getGbxInformation($this->directory.$this->filename, false);

        if (!$this->gbx || !isset($this->gbx->MapUid)) {
            throw new \Exception('Failed to load GBX information of file "'.$this->directory.$this->filename.'".');
        }

        $this->uid = $this->gbx->MapUid;
    }

    /**
     * Gets the filename with directory.
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->directory.$this->filename;
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

        $download = RestClient::get('http://tm.mania-exchange.com/tracks/download/'.$mxId);

        if ($download->getStatusCode() != 200) {
            throw new \Exception("Download $mxId failed: ".$download->getReasonPhrase());
        }

        Log::write("Request $mxId finished.", isVeryVerbose());

        if ($download->getHeader('Content-Type')[0] != 'application/x-gbx') {
            throw new \Exception('File is not a valid GBX.');
        }

        $filename = preg_replace('/^attachment; filename="(.+)"$/', '\1',
            $download->getHeader('content-disposition')[0]);
        $filename = html_entity_decode(trim($filename), ENT_QUOTES | ENT_HTML5);
        $filename = preg_replace('/[^a-z0-9\-\_\#\ \.]/i', '', $filename);
        $filename = preg_replace('/\ /i', '_', $filename);

        Log::write('Saving new map as '.MapController::getMapsPath($filename), isVerbose());

        File::put(MapController::getMapsPath($filename), $download->getBody()->getContents());

        if (!File::exists(MapController::getMapsPath($filename))) {
            throw new \Exception('Map download failed, map does not exist.');
        }

        $mxMap = new MxMap();
        $mxMap->filename = $filename;
        $mxMap->directory = '';
        $mxMap->mxId = $mxId;

        return $mxMap;
    }

    /**
     * String representation of object
     * @link https://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return json_encode($this);
    }

    /**
     * Constructs the object
     * @link https://php.net/manual/en/serializable.unserialize.php
     * @param  string  $serialized  <p>
     * The string representation of the object.
     * </p>
     * @return MxMap
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $data = json_decode($serialized);

        $mxMap = new MxMap();
        $mxMap->directory = '';
        $mxMap->filename = $data->filename;
        $mxMap->gbxString = $data->gbxString;
        $mxMap->gbx = $data->gbx;
        $mxMap->uid = $data->uid;
        $mxMap->mxId = $data->mxId;
        $mxMap->mxDetails = $data->mxDetails;

        return $mxMap;
    }
}