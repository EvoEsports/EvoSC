<?php

$escVersion = '0.41.9';
$serverName = 'loading...';

include 'global-functions.php';

esc\Classes\Config::loadConfigFiles();

function startEsc(Symfony\Component\Console\Output\OutputInterface $output)
{
    global $serverName;

    \esc\Classes\Log::setOutput($output);

    try {
        esc\Classes\Log::info("Connecting to server...");

        esc\Classes\Server::init(
            config('server.ip'),
            config('server.port'),
            5,
            config('server.rpc.login'),
            config('server.rpc.password')
        );

        $serverName = \esc\Classes\Server::getRpc()->getServerName();

        if (!\esc\Classes\Server::isAutoSaveValidationReplaysEnabled()) {
            \esc\Classes\Server::autoSaveValidationReplays(true);
        }
        if (!\esc\Classes\Server::isAutoSaveReplaysEnabled()) {
            \esc\Classes\Server::autoSaveReplays(true);
        }

        esc\Classes\Log::info("Connection established.");
    } catch (\Exception $e) {
        esc\Classes\Log::error("Connection to server failed.");
        exit(1);
    }

    \esc\Classes\Timer::setInterval(config('server.controller-interval') ?? 100);

    esc\Classes\Database::init();
    esc\Classes\RestClient::init(serverName());
    esc\Controllers\HookController::init();
    esc\Controllers\TemplateController::init();
    esc\Controllers\ChatController::init();
    esc\Classes\ManiaLinkEvent::init();
    esc\Controllers\GroupController::init();
    esc\Controllers\AccessController::init();
    esc\Controllers\MapController::init();
    esc\Controllers\PlayerController::init();
    \esc\Controllers\KeyController::init();
    esc\Classes\Vote::init();
    esc\Controllers\ModuleController::init();
    \esc\Controllers\HideScriptController::init();
    \esc\Controllers\PlanetsController::init();
}

function bootModules()
{
    esc\Controllers\ModuleController::bootModules();
}

function beginMap()
{
    $map = \esc\Models\Map::where('filename', esc\Classes\Server::getCurrentMapInfo()->fileName)->first();
    esc\Classes\Hook::fire('BeginMap', $map);
}