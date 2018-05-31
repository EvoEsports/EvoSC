<?php

include 'autoload.php';
include 'bootstrap.php';

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
        $this->playLoop();
    }

    private function playLoop()
    {
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
            } catch (Exception $e) {
                $crashReport = collect();
                $crashReport->put('file', $e->getFile());
                $crashReport->put('line', $e->getLine());
                $crashReport->put('message', $e->getMessage());
                $crashReport->put('trace', $e->getTraceAsString());

                if (!is_dir('../crash-reports')) {
                    mkdir('../crash-reports');
                }

                $filename = sprintf('../crash-reports/%s.json', date('Y-m-d_Hi', time()));
                file_put_contents($filename, $crashReport->toJson());
            }
        }
    }
}