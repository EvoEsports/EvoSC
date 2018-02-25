<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE);

include 'core/autoload.php';
include 'vendor/autoload.php';

include 'core/bootstrap.php';

while (true) {
    try {
        esc\classes\Log::info("Starting...");

        startEsc();

        loadModulesFrom('core\\Modules');
        loadModulesFrom('modules');

        beginMap();

//        \esc\controllers\ChatController::messageAllNew('ESC Started. Running version ', '$' . config('color.secondary') . '0.10.4');

        while (true) {
            cycle();
        }
    } catch (PDOException $pdoe) {
        esc\classes\Log::error("Database exception: $pdoe");
    } catch (\Exception $e) {
        \esc\classes\Log::error("Fatal error. Restarting...");
        \esc\classes\Log::error($e, false);
    }
}