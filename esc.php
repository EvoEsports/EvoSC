<?php

use esc\classes\Config;
use esc\classes\Log;
use esc\classes\Timer;
use esc\classes\ModuleHandler;
use \esc\classes\EventHandler;
use Maniaplanet\DedicatedServer\Connection;

include 'core/autoload.php';
include 'vendor/autoload.php';

Log::info("Starting...");

try {
    ModuleHandler::loadModules('core/modules');
//    ModuleHandler::loadModules('modules');

    Log::info("Loading config files.");
    Config::loadConfigFiles();

    try{
        Log::info("Connecting to server...");
        $rpc = Connection::factory(Config::get('server.ip'), Config::get('server.port'), 5, Config::get('server.rpc.login'), Config::get('server.rpc.password'));
        $rpc->enableCallbacks();
        Log::info("Connection established.");
    }catch(\Exception $e){
        Log::error("Connection to server failed.");
        throw new Exception("Connection to server failed.");
    }

    while (true) {
        Timer::startCycle();

        EventHandler::handleCallbacks($rpc->executeCallbacks());

        usleep(Timer::getNextCyclePause());
    }
} catch (Exception $e) {
    Log::error("Fatal error. Restarting...");
}

Log::info("Shutting down.");