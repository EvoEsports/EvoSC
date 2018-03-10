<?php

$escVersion = '0.14.*';

include 'global-functions.php';

esc\classes\Log::info("Loading config files.");
esc\classes\Config::loadConfigFiles();

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
        esc\classes\Log::info("Connecting to server...");

        esc\classes\Server::init(
            esc\classes\Config::get('server.ip'),
            esc\classes\Config::get('server.port'),
            5,
            esc\classes\Config::get('server.rpc.login'),
            esc\classes\Config::get('server.rpc.password')
        );

        esc\classes\Server::getStatus();

        if(!\esc\Classes\Server::isAutoSaveValidationReplaysEnabled()){
            \esc\Classes\Server::autoSaveValidationReplays(true);
        }
        if(!\esc\Classes\Server::isAutoSaveReplaysEnabled()){
            \esc\Classes\Server::autoSaveReplays(true);
        }

        esc\classes\Log::info("Connection established.");
    } catch (\Exception $e) {
        esc\classes\Log::error("Connection to server failed.");
        exit(1);
    }

    esc\classes\Database::init();
    esc\classes\RestClient::init(config('server.name'));
    esc\controllers\HookController::init();
    esc\controllers\TemplateController::init();
    esc\controllers\ChatController::init();
    esc\classes\ManiaLinkEvent::init();
    esc\controllers\GroupController::init();
    esc\controllers\MapController::init();
    esc\controllers\PlayerController::init();
    esc\classes\Vote::init();
    esc\controllers\ModuleController::init();

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
    foreach (esc\classes\Server::getPlayerList() as $player) {
        $ply = \esc\models\Player::firstOrCreate(['Login' => $player->login]);
        $ply->update($player->toArray());
        esc\controllers\PlayerController::playerConnect($ply, true);
    }
}

function cycle()
{
    global $musicServer;

    esc\classes\Timer::startCycle();
    esc\controllers\HookController::handleCallbacks(esc\classes\Server::executeCallbacks());
    usleep(esc\classes\Timer::getNextCyclePause());

    if(config('music.enable-internal-server', true)) {
        $msOutput = $musicServer->getOutput();
        if ($msOutput) {
            \esc\Classes\Log::music($msOutput);
        }
    }
}

function loadModulesFrom(string $path)
{
    esc\controllers\ModuleController::loadModules($path);
}

function beginMap()
{
    $map = \esc\models\Map::where('FileName', esc\classes\Server::getCurrentMapInfo()->fileName)->first();
    esc\controllers\HookController::fire('BeginMap', [$map]);
}