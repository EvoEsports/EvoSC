<?php

// if(!preg_match('/^ManiaPlanet\/.+/', $_SERVER['HTTP_USER_AGENT'])){
//     header("HTTP/1.1 403 Unauthorized");
//     die('Unauthorized');
// }

require __DIR__ . '/vendor/autoload.php';

if (isset($_GET['song']) && file_exists(__DIR__ . '/' . $_GET['song'])) {
    header("HTTP/1.1 200 Success");
    header("Content-Type: audio/ogg");
    echo file_get_contents(__DIR__ . '/' . $_GET['song']);

    return;
}

$files = collect(scandir(__DIR__))->filter(function ($item) {
    return preg_match('/\.ogg$/', $item);
});

$hash = md5($files);

if (file_exists(__DIR__ . '/music.lib')) {
    $snapshot = json_decode(file_get_contents(__DIR__ . '/music.lib'));
    if ($hash == $snapshot->hash) {
        header("HTTP/1.1 200 Success");
        echo json_encode($snapshot->data);
        exit(0);
    }
}

$songs = new \Tightenco\Collect\Support\Collection();

foreach ($files as $file) {
    $getID3 = new getID3();
    $info   = collect($getID3->analyze(__DIR__ . '/' . $file))->only(['playtime_string', 'tags', 'filename']);

    $tags = collect($info->get('tags')['vorbiscomment']);

    $songs->push([
        'title'  => $tags->get('title')[0] ?: 'n/a',
        'artist' => $tags->get('artist')[0] ?: 'n/a',
        'album'  => $tags->get('album')[0] ?: 'n/a',
        'file'   => $info->get('filename'),
        'length' => $info->get('playtime_string'),
    ]);
}

file_put_contents(__DIR__ . '/music.lib', json_encode([
    'hash' => $hash,
    'data' => $songs,
]));

header("HTTP/1.1 200 Success");
echo $songs->toJson();
exit(0);
