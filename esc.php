<?php

//error_reporting(E_ERROR | E_WARNING | E_PARSE);

include 'core/autoload.php';
include 'vendor/autoload.php';

include 'core/bootstrap.php';

while (true) {
    try {
        esc\Classes\Log::info("Starting...");

        startEsc();

        loadModulesFrom('core/Modules');
        loadModulesFrom('modules');

        beginMap();

        while (true) {
            cycle();
        }
    } catch (PDOException $pdoe) {
        esc\Classes\Log::error("Database exception: $pdoe");
    } catch (\Exception $e) {
        \esc\Classes\Log::error("Fatal error. Restarting...");
        \esc\Classes\Log::error($e, false);
    }
}