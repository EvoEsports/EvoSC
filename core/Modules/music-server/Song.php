<?php

class Song
{
    public $title;
    public $artist;
    public $album;
    public $year;
    public $length;
    public $bitrate;
    public $url;

    public function __construct($songInformation = null, $url = null)
    {
        $this->bitrate = (float)$songInformation['audio']['bitrate'] ?: -1.0;
        $this->title = (string)$songInformation['tags']['vorbiscomment']['title'][0] ?: 'unknown';
        $this->artist = (string)$songInformation['tags']['vorbiscomment']['artist'][0] ?: 'unknown';
        $this->album = (string)$songInformation['tags']['vorbiscomment']['album'][0] ?: 'unknown';
        $this->year = (string)$songInformation['tags']['vorbiscomment']['date'][0] ?: 'unknown';
        $this->length = (string)$songInformation['playtime_string'] ?: 'unknown';
        $this->url = $url;
    }

    public static function createFromCachefile(string $jsonData): ?Song
    {
        $data = json_decode($jsonData);

        $song = new Song();

        foreach ($data as $key => $value) {
            $song->{$key} = $value;
        }

        return $song;
    }
}