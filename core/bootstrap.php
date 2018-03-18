<?php

$escVersion = '0.14.*';

include 'global-functions.php';

$output = new \Symfony\Component\Console\Output\ConsoleOutput();
\esc\Classes\Log::setOutput($output);

esc\Classes\Log::info("Loading config files.");
esc\Classes\Config::loadConfigFiles();

if(config('music.enable-internal-server', true)){
    \esc\Classes\Log::info("Starting music server...");

    $phpBinaryFinder = new Symfony\Component\Process\PhpExecutableFinder();
    $phpBinaryPath = $phpBinaryFinder->find();

    $musicServer = new Symfony\Component\Process\Process($phpBinaryPath . ' -S 0.0.0.0:6600 ' . coreDir('music-server.php'));
    $musicServer->start();

    \esc\Classes\Log::info("Music server started.");
}

function startEsc()
{
    try {
        esc\Classes\Log::info("Connecting to server...");

        esc\Classes\Server::init(
            esc\Classes\Config::get('server.ip'),
            esc\Classes\Config::get('server.port'),
            5,
            esc\Classes\Config::get('server.rpc.login'),
            esc\Classes\Config::get('server.rpc.password')
        );

        esc\Classes\Server::getStatus();

        if(!\esc\Classes\Server::isAutoSaveValidationReplaysEnabled()){
            \esc\Classes\Server::autoSaveValidationReplays(true);
        }
        if(!\esc\Classes\Server::isAutoSaveReplaysEnabled()){
            \esc\Classes\Server::autoSaveReplays(true);
        }

        esc\Classes\Log::info("Connection established.");
    } catch (\Exception $e) {
        esc\Classes\Log::error("Connection to server failed.");
        exit(1);
    }

    esc\Classes\Database::init();
    esc\Classes\RestClient::init(config('server.name'));
    esc\Controllers\HookController::init();
    esc\Controllers\TemplateController::init();
    esc\Controllers\ChatController::init();
    esc\Classes\ManiaLinkEvent::init();
    esc\Controllers\GroupController::init();
    esc\Controllers\AccessController::init();
    esc\Controllers\MapController::init();
    esc\Controllers\PlayerController::init();
    esc\Classes\Vote::init();
    esc\Controllers\ModuleController::init();

    \esc\Classes\Template::add('esc.box', \esc\Classes\File::get(__DIR__ . '/Templates/ranking-box.latte.xml'));
    \esc\Classes\Template::add('esc.ranking', \esc\Classes\File::get(__DIR__ . '/Templates/Components/ranking.latte.xml'));
    \esc\Classes\Template::add('esc.modal', \esc\Classes\File::get(__DIR__ . '/Templates/Components/modal.latte.xml'));
    \esc\Classes\Template::add('esc.pagination', \esc\Classes\File::get(__DIR__ . '/Templates/Components/pagination.latte.xml'));
    \esc\Classes\Template::add('blank', \esc\Classes\File::get(__DIR__ . '/Templates/blank.latte.xml'));

    $settings = \esc\Classes\Server::getModeScriptSettings();
    $settings['S_TimeLimit'] = config('server.roundTime', 7) * 60;
    \esc\Classes\Server::setModeScriptSettings($settings);

    \esc\Models\Player::whereOnline(true)->update(['Online' => false]);

    //Handle already connected players
    foreach (esc\Classes\Server::getPlayerList() as $player) {
        $ply = \esc\Models\Player::firstOrCreate(['Login' => $player->login]);
        $ply->update($player->toArray());
        esc\Controllers\PlayerController::playerConnect($ply, true);
    }
}

function cycle()
{
    global $musicServer;

    esc\Classes\Timer::startCycle();
    esc\Controllers\HookController::handleCallbacks(esc\Classes\Server::executeCallbacks());
    usleep(esc\Classes\Timer::getNextCyclePause());

    if(config('music.enable-internal-server', true)) {
        $msOutput = $musicServer->getOutput();
        if ($msOutput) {
            \esc\Classes\Log::music($msOutput);
        }
    }
}

function loadModulesFrom(string $path)
{
    esc\Controllers\ModuleController::loadModules($path);
}

function beginMap()
{
    $map = \esc\Models\Map::where('FileName', esc\Classes\Server::getCurrentMapInfo()->fileName)->first();
    esc\Controllers\HookController::fire('BeginMap', [$map]);
}