<?php

$escVersion = '0.29.*';

include 'global-functions.php';

$output = new \Symfony\Component\Console\Output\ConsoleOutput();
\esc\Classes\Log::setOutput($output);

esc\Classes\Log::info("Loading config files.");
esc\Classes\Config::loadConfigFiles();

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
    esc\Classes\RestClient::init(config('server.name'));
    esc\Controllers\HookController::init();
    esc\Controllers\TemplateController::init();
    esc\Controllers\ChatController::init();
    esc\Classes\ManiaLinkEvent::init();
    esc\Controllers\GroupController::init();
    esc\Controllers\AccessController::init();
    esc\Controllers\MapController::init();
    esc\Controllers\PlayerController::init();
    \esc\Controllers\SpectatorController::init();
    \esc\Controllers\KeyController::init();
    esc\Classes\Vote::init();
    esc\Controllers\ModuleController::init();

    \esc\Classes\Template::add('esc.box', \esc\Classes\File::get(__DIR__ . '/Templates/ranking-box.latte.xml'));
    \esc\Classes\Template::add('esc.ranking', \esc\Classes\File::get(__DIR__ . '/Templates/Components/ranking.latte.xml'));
    \esc\Classes\Template::add('esc.modal', \esc\Classes\File::get(__DIR__ . '/Templates/Components/modal.latte.xml'));
    \esc\Classes\Template::add('esc.pagination', \esc\Classes\File::get(__DIR__ . '/Templates/Components/pagination.latte.xml'));
    \esc\Classes\Template::add('esc.box2', \esc\Classes\File::get(__DIR__ . '/Templates/Components/box.latte.xml'));
    \esc\Classes\Template::add('esc.stat-list', \esc\Classes\File::get(__DIR__ . '/Templates/Components/stat-list.latte.xml'));
    \esc\Classes\Template::add('esc.icon-box', \esc\Classes\File::get(__DIR__ . '/Templates/Components/icon-box.latte.xml'));
    \esc\Classes\Template::add('esc.hide-script', \esc\Classes\File::get(__DIR__ . '/Templates/Scripts/hide.script.txt'));
    \esc\Classes\Template::add('blank', \esc\Classes\File::get(__DIR__ . '/Templates/blank.latte.xml'));

    \esc\Controllers\ChatController::addCommand('config', 'Config::configReload', 'Reload config', '//', 'config');

    $settings = \esc\Classes\Server::getModeScriptSettings();
    $settings['S_TimeLimit'] = config('server.roundTime', 7) * 60;
    \esc\Classes\Server::setModeScriptSettings($settings);

    \esc\Models\Player::whereOnline(true)->update(['Online' => false]);

    //Handle already connected players
    foreach (onlinePlayers() as $player) {
        esc\Controllers\PlayerController::playerConnect($player, true);
    }
}

function cycle()
{
    esc\Classes\Timer::startCycle();
    esc\Controllers\HookController::handleCallbacks(esc\Classes\Server::executeCallbacks());
    usleep(esc\Classes\Timer::getNextCyclePause());
}

function bootModules()
{
    esc\Controllers\ModuleController::bootModules();
}

function migrate()
{
    $migrations = classes()
        ->where('dir', 'Migrations')
        ->sortBy('class')
        ->pluck('file','class');

    dd($migrations);
}

function beginMap()
{
    $map = \esc\Models\Map::where('FileName', esc\Classes\Server::getCurrentMapInfo()->fileName)->first();
    esc\Controllers\HookController::fire('BeginMap', [$map]);
}