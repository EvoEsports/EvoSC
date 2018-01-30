<?php

use esc\classes\Config;
use esc\classes\Log;
use esc\classes\Timer;
use esc\classes\ModuleHandler;
use \esc\classes\EventHandler;
use Maniaplanet\DedicatedServer\Connection;
use Illuminate\Database\Capsule\Manager as Capsule;

include 'core/autoload.php';
include 'vendor/autoload.php';

Log::info("Starting...");

try {
    ModuleHandler::loadModules('core/modules');
//    ModuleHandler::loadModules('modules');

    Log::info("Loading config files.");
    Config::loadConfigFiles();

    \esc\controllers\HookController::initialize();

    Log::info("Connecting to database...");
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => Config::get('db.host'),
        'database'  => Config::get('db.db'),
        'username'  => Config::get('db.user'),
        'password'  => Config::get('db.password'),
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
    ]);

    // Make this Capsule instance available globally via static methods... (optional)
    $capsule->setAsGlobal();

    // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
    $capsule->bootEloquent();
    Log::info("Database connected.");

    \esc\controllers\PlayerController::initialize();

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

        \esc\controllers\HookController::handleCallbacks($rpc->executeCallbacks());

        usleep(Timer::getNextCyclePause());
    }
} catch (Exception $e) {
    Log::error("Fatal error. Restarting...");
}

Log::info("Shutting down.");