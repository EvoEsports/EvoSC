<?php

include 'global-functions.php';

esc\classes\Log::info("Loading config files.");
esc\classes\Config::loadConfigFiles();

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

        esc\classes\Server::getRpc()->getStatus();

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

    \esc\Models\Player::whereOnline(true)->update(['Online' => false]);

    //Handle already connected players
    foreach (esc\classes\Server::getRpc()->getPlayerList() as $player) {
        $ply = \esc\models\Player::firstOrCreate(['Login' => $player->login]);
        $ply->update($player->toArray());
        esc\controllers\PlayerController::playerConnect($ply, true);
    }
}

function cycle()
{
    esc\classes\Timer::startCycle();
    esc\controllers\HookController::handleCallbacks(esc\classes\Server::executeCallbacks());
    usleep(esc\classes\Timer::getNextCyclePause());
}

function loadModulesFrom(string $path)
{
    esc\controllers\ModuleController::loadModules($path);
}

function beginMap()
{
    $map = \esc\models\Map::where('FileName', esc\classes\Server::getRpc()->getCurrentMapInfo()->fileName)->first();
    esc\controllers\HookController::fire('BeginMap', [$map]);
}