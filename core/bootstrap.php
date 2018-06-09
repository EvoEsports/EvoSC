<?php

$escVersion = '0.39.9';
$serverName = 'loading...';

include 'global-functions.php';

$output = new \Symfony\Component\Console\Output\ConsoleOutput();
\esc\Classes\Log::setOutput($output);

esc\Classes\Log::info("Loading config files.");
esc\Classes\Config::loadConfigFiles();

function startEsc()
{
    global $serverName;

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

function cycle()
{
    set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext) {
        // error was suppressed with the @-operator
        if (0 === error_reporting()) {
            return false;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    esc\Classes\Timer::startCycle();

    try {
        \esc\Controllers\EventController::handleCallbacks(esc\Classes\Server::executeCallbacks());
    } catch (Exception $e) {
        $crashReport = collect();
        $crashReport->put('file', $e->getFile());
        $crashReport->put('line', $e->getLine());
        $crashReport->put('message', $e->getMessage() . "\n" . $e->getTraceAsString());

        if (!is_dir(__DIR__ . '/../crash-reports')) {
            mkdir(__DIR__ . '/../crash-reports');
        }

        $filename = sprintf(__DIR__ . '/../crash-reports/%s.json', date('Y-m-d_Hi', time()));
        file_put_contents($filename, $crashReport->toJson());
    }

    usleep(esc\Classes\Timer::getNextCyclePause());
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