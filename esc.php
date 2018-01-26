<?php

use esc\classes\Log;
use esc\classes\Timer;
use esc\classes\ModuleHandler;

include 'core/autoload.php';
include 'vendor/autoload.php';

Log::info("Starting...");

try {
    ModuleHandler::loadModules('core/modules');
    ModuleHandler::loadModules('modules');

    while (true) {
        Timer::startCycle();

        \esc\classes\EventHandler::callEvent('tick');

        usleep(Timer::getNextCyclePause());
    }
} catch (Exception $e) {
    Log::error("Fatal error. Restarting...");
}

Log::info("Shutting down.");