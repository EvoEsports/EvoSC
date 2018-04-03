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

        //Enable mode script rpc-callbacks else you wont get stuf flike checkpoints and finish
        //only if you would enable legacy callbacks and we don't want that
        \esc\Classes\Server::triggerModeScriptEventArray('XmlRpc.EnableCallbacks', ['true']);

        while (true) {
            cycle();
        }
    } catch (PDOException $pdoe) {
        esc\Classes\Log::error("Database exception: $pdoe");
    } catch (\Exception $e) {
        \esc\Classes\Server::call('ChatEnableManualRouting', [false, false]);
        \esc\Classes\Log::error("!!!!! Fatal error. Restarting... Check the logs for more detailed information !!!!!");
        \esc\Classes\Log::error($e, true);
    }
}