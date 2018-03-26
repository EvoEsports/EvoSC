<?php

//error_reporting(E_ERROR | E_WARNING | E_PARSE);

include 'core/autoload.php';
include 'vendor/autoload.php';

include 'core/bootstrap.php';

while (true) {
    try {
        esc\Classes\Log::info("Starting...");

        startEsc();

        loadModulesFrom(__DIR__ . '/core/Modules');
        loadModulesFrom(__DIR__ . '/modules');

        beginMap();

        while (true) {
            cycle();
        }
    } catch (PDOException $pdoe) {
        esc\Classes\Log::error("Database exception: $pdoe");
    } catch (\Exception $e) {
        \esc\Classes\Log::error("!!!!! Fatal error. Restarting... Check the logs for more detailed information !!!!!");
        \esc\Classes\Log::error($e, false);

        try{
            mail('brakerb@vination.eu', '[ESC] Fatal error', $e->getTraceAsString());
        }catch(\Exception $e){
        }
    }
}