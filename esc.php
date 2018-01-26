<?php

use esc\classes\Log;
use esc\classes\Timer;
use esc\classes\ModuleHandler;
use esc\exceptions\FatalErrorException;

include 'core/autoload.php';
include 'vendor/autoload.php';

Log::info("Starting...");

while (true) {
    try {
        ModuleHandler::loadModules();

        Timer::startCycle();

        break;

        usleep(Timer::getNextCyclePause());
    } catch (FatalErrorException $fee) {
        Log::error("Fatal error. Restarting...");
    }
}

Log::info("Shutting down.");