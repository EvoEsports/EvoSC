<?php

include 'autoload.php';
include 'bootstrap.php';

use esc\Classes\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EscRun extends Command
{
    protected function configure()
    {
        $this->setName('run');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->start();
    }

    private function start()
    {
        register_shutdown_function(function () {
            $error = error_get_last();

            echo $error['type'] . "\n";

            // fatal error, E_ERROR === 1
            if ($error['type'] === E_ERROR) {
                $crashReport = collect();
                $crashReport->put('file', $error['file']);
                $crashReport->put('line', $error['line']);
                $crashReport->put('message', $error['message']);

                if (!is_dir(__DIR__ . '/../crash-reports')) {
                    mkdir(__DIR__ . '/../crash-reports');
                }

                $filename = sprintf(__DIR__ . '/../crash-reports/%s.json', date('Y-m-d_Hi', time()));
                file_put_contents($filename, $crashReport->toJson());

                $this->start();
            }
        });

        esc\Classes\Log::info("Starting...");

        startEsc();
        bootModules();
        beginMap();

        //Set connected players online
        foreach (onlinePlayers() as $player) {
            esc\Controllers\PlayerController::playerConnect($player, true);
        }

        //Enable mode script rpc-callbacks else you wont get stuf flike checkpoints and finish
        \esc\Classes\Server::triggerModeScriptEventArray('XmlRpc.EnableCallbacks', ['true']);

        while (true) {
            try {
                cycle();
            } catch (\Maniaplanet\DedicatedServer\Xmlrpc\TransportException $e) {
                Log::logAddLine('XmlRpc', $e->getMessage());
            }
        }
    }
}