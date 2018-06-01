<?php

include_once 'global-functions.php';

$musicDir = musicDir();

if (preg_match('/^\/(.+\.ogg)$/', $_SERVER["REQUEST_URI"], $matches)) {
    $file = $musicDir . '/' . urldecode($matches[1]);
    header('Content-type: audio/ogg');
    echo file_get_contents($file);
    exit(0);
}

if (!is_dir($musicDir)) {
    mkdir($musicDir);
}

require __DIR__ . '/../vendor/autoload.php';

$files = collect(scandir($musicDir))->filter(function ($item) {
    return preg_match('/\.ogg$/', $item);
});

$songs = new \Illuminate\Support\Collection();

foreach ($files as $file) {
    $getID3 = new getID3;
    $info = collect($getID3->analyze($musicDir . '/' . $file))->only(['playtime_string', 'tags', 'filename']);

    $tags = collect($info->get('tags')['vorbiscomment']);

    $songs->push([
        'title' => $tags->get('title')[0],
        'artist' => $tags->get('artist')[0],
        'album' => $tags->get('album')[0],
        'file' => $info->get('filename'),
        'length' => $info->get('playtime_string')
    ]);
}

header("HTTP/1.1 200 Success");
echo $songs;
exit(0);