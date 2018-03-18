<?php

$uaseco_host = '127.0.0.1';
$uaseco_db = 'uaseco';
$uaseco_user = 'root';
$uaseco_password = '';
$uaseco_table_prefix = 'drtk03_';

include 'core/autoload.php';
include 'vendor/autoload.php';
include 'core/bootstrap.php';

include 'core/Modules/stats/Models/Stats.php';
include 'core/Modules/stats/Statistics.php';
include 'core/Modules/mx-karma/Models/Karma.php';
include 'core/Modules/mx-karma/MxKarma.php';
include 'core/Modules/local-records/Models/LocalRecord.php';
include 'core/Modules/local-records/LocalRecords.php';

use esc\Models\Player;
use Illuminate\Database\Capsule\Manager as Capsule;

$output = \esc\Classes\Log::getOutput();

$uaseco = new Capsule();
$uaseco->addConnection([
    'driver' => 'mysql',
    'host' => $uaseco_host,
    'database' => $uaseco_db,
    'username' => $uaseco_user,
    'password' => $uaseco_password,
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => $uaseco_table_prefix
]);

//$uaseco->bootEloquent();

$connection = $uaseco->getConnection();

\esc\Classes\Database::init();
\esc\Controllers\PlayerController::createTables();
Statistics::createTables();

\esc\Classes\Log::info('Importing authors');
$authors = $connection->table('authors')->get();
$bar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($authors));
$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');

$bar->start();
foreach ($authors as $author) {

    $ply = \esc\Models\Player::where('Login', $author->Login)->first();

    if (!$ply) {
        \esc\Models\Player::create([
            'Login' => $author->Login,
            'NickName' => $author->Nickname
        ]);
    }

    $bar->advance();
}

$bar->finish();
echo "\n";

\esc\Classes\Log::info('Importing players');
$players = $connection->table('players')->get();
$bar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($players));
$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');

$bar->start();
foreach ($players as $player) {

    $ply = \esc\Models\Player::where('Login', $player->Login)->first();

    if (!$ply) {
        $ply = \esc\Models\Player::create(['Login' => $player->Login, 'NickName' => $player->Nickname]);
        $ply = \esc\Models\Player::where('Login', $player->Login)->first();
    }

    $stat = Stats::create([
        'Player' => $ply->id,
        'Visits' => $player->Visits,
        'Wins' => $player->Wins,
        'Donations' => $player->Donations,
        'Playtime' => floor($player->TimePlayed / 60),
        'Finishes' => $player->MostFinished,
        'Locals' => $player->MostRecords,
        'updated_at' => $player->LastVisit,
        'created_at' => $player->LastVisit
    ]);

    $bar->advance();
}

$bar->finish();
echo "\n";


\esc\Classes\Log::info('Importing maps');
$maps = $connection->table('maps')->get();
$bar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($maps));
$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');

\esc\Controllers\MapController::createTables();

$bar->start();
foreach ($maps as $map) {

    $author = Player::where('Login', $connection->table('authors')
        ->where('AuthorId', $map->AuthorId)
        ->first()
        ->Login)
        ->first();

    $uasecoMap = (array)$map;
    $uasecoMap['FileName'] = $map->Filename;
    $uasecoMap['UId'] = $map->Uid;
    $uasecoMap['LapRace'] = $map->MultiLap == 'true' ? 1 : 0;
    $uasecoMap['Author'] = $author->id;
    $uasecoMap['LastPlayed'] = \Carbon\Carbon::yesterday(); //Make it instantly available

    if (preg_match('/MX\/_(\d+)\.Map\.Gbx/i', $map->Filename, $matches)) {
        $uasecoMap['MxId'] = $matches[1];
    }

    \esc\Models\Map::create($uasecoMap);

    $bar->advance();
}

$bar->finish();
echo "\n";

MxKarma::createTables();

\esc\Classes\Log::info('Importing map ratings');
$ratings = $connection->table('ratings')->get();
$bar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($ratings));
$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');


$bar->start();
foreach ($ratings as $rating) {

    $mxKarma = [0, 20, 40, null, 60, 80, 100][$rating->Score + 3];

    $ply = Player::where('Login', $connection->table('players')
        ->where('PlayerId', $rating->PlayerId)
        ->first()
        ->Login)
        ->first();

    $map = \esc\Models\Map::where('FileName', $connection->table('maps')
        ->where('MapId', $rating->MapId)
        ->first()
        ->Filename)
        ->first();

    Karma::create([
        'Player' => $ply->id,
        'Map' => $map->id,
        'rating' => $mxKarma
    ]);
}

$bar->finish();
echo "\n";

\esc\Classes\Log::info('Importing local records');
LocalRecords::createTables();

$rankCount = 1000;
$records = $connection->table('records')->get();
$bar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($records));
$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');

$bar->start();
foreach ($records as $record) {

    $ply = Player::where('Login', $connection->table('players')
        ->where('PlayerId', $record->PlayerId)
        ->first()
        ->Login)
        ->first();

    $map = \esc\Models\Map::where('FileName', $connection->table('maps')
        ->where('MapId', $record->MapId)
        ->first()
        ->Filename)
        ->first();

    LocalRecord::create([
        'Map' => $map->id,
        'Player' => $ply->id,
        'Score' => $record->Score,
        'Checkpoints' => $record->Checkpoints,
        'Rank' => $rankCount++
    ]);

    $bar->advance();
}

$bar->finish();
echo "\n";

\esc\Classes\Log::info('Fixing local record ranks');
$bar = new \Symfony\Component\Console\Helper\ProgressBar($output, \esc\Models\Map::count());
$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');

$bar->start();
foreach (\esc\Models\Map::all() as $map) {

    $i = 1;
    $bar->setMessage('Fixing local ranks for ' . stripAll($map->Name));

    foreach ($map->locals->sortBy('Score') as $local) {
        $local->update(['Rank' => $i]);
        $i++;
    }

    $bar->advance();
}

$bar->finish();
echo "\n";

\esc\Classes\Log::info('Creating groups');
\esc\Controllers\GroupController::createTables();
\esc\Classes\Log::info('Creating access rights');
\esc\Controllers\AccessController::createTables();
