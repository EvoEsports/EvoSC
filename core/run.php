<?php

require 'autoload.php';
require 'global-functions.php';

use esc\Classes\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EscRun extends Command
{
    protected function configure()
    {
        $this->setName('run')
             ->setDescription('Run Evo Server Controller')
             ->addOption('daemon', 'd', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Run command as daemon.', null);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        global $escVersion;
        global $serverName;

        $escVersion = '0.47.0';

        esc\Classes\Config::loadConfigFiles();

        //Check that cache directory exists
        if (!is_dir(cacheDir())) {
            mkdir(cacheDir());
        }

        //Check that logs directory exists
        if (!is_dir(logDir())) {
            mkdir(logDir());
        }

        try {
            $output->writeln("Connecting to server...");

            esc\Classes\Server::init(
                config('server.ip'),
                config('server.port'),
                5,
                config('server.rpc.login'),
                config('server.rpc.password')
            );

            $serverName = \esc\Classes\Server::rpc()->getServerName();

            if (!\esc\Classes\Server::isAutoSaveValidationReplaysEnabled()) {
                \esc\Classes\Server::autoSaveValidationReplays(true);
            }
            if (!\esc\Classes\Server::isAutoSaveReplaysEnabled()) {
                \esc\Classes\Server::autoSaveReplays(true);
            }

            $output->writeln("Connection established.");
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $output->writeln("<error>$msg</error>");
            exit(1);
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        file_put_contents(baseDir(config('server.login') . '_evosc.pid'), getmypid());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \esc\Classes\Log::setOutput($output);

        $version = getEscVersion();
        $motd    = "      ______           _____ ______
     / ____/  _______ / ___// ____/
    / __/| | / / __ \\__ \/ /     
   / /___| |/ / /_/ /__/ / /___   
  /_____/|___/\____/____/\____/  $version
";

        $output->writeln("<fg=cyan;options=bold>$motd</>");

        esc\Classes\Log::info("Starting...");

        \esc\Classes\Timer::setInterval(config('server.controller-interval') ?? 100);

        esc\Classes\Database::init();
        esc\Classes\RestClient::init(serverName());
        esc\Controllers\HookController::init();
        esc\Controllers\TemplateController::init();
        esc\Controllers\ChatController::init();
        esc\Classes\ManiaLinkEvent::init();
        esc\Controllers\GroupController::init();
        esc\Controllers\AccessController::init();
        esc\Controllers\MapController::init();
        esc\Controllers\PlayerController::init();
        \esc\Controllers\KeyController::init();
        esc\Controllers\ModuleController::init();
        \esc\Controllers\PlanetsController::init();

        \esc\Controllers\ChatController::addCommand('setconfig', [\esc\Classes\Config::class, 'setChatCmd'], 'Sets config value', '//', 'config');

        if (isVerbose()) {
            Log::logAddLine('BOOT', 'Booting core finished.', true);
        }

        esc\Controllers\ModuleController::bootModules();

        if (isVerbose()) {
            Log::logAddLine('BOOT', 'Booting modules finished.', true);
        }

        $map = \esc\Models\Map::where('filename', esc\Classes\Server::getCurrentMapInfo()->fileName)->first();
        esc\Classes\Hook::fire('BeginMap', $map);

        //Set connected players online
        \esc\Models\Player::where('player_id', '>', 0)->update(['player_id' => 0]);
        $playerList = collect(\esc\Classes\Server::rpc()->getPlayerList());

        foreach ($playerList as $maniaPlayer) {
            $player = \esc\Models\Player::whereLogin($maniaPlayer->login)->first();

            if ($player) {
                \esc\Classes\Hook::fire('PlayerConnect', $player);
            }
        }

        //Enable mode script rpc-callbacks else you wont get stuf flike checkpoints and finish
        \esc\Classes\Server::triggerModeScriptEventArray('XmlRpc.EnableCallbacks', ['true']);
        \esc\Classes\Server::rpc()->disableServiceAnnounces(true);

        while (true) {
            try {
                esc\Classes\Timer::startCycle();

                try {
                    \esc\Controllers\EventController::handleCallbacks(esc\Classes\Server::executeCallbacks());
                } catch (Exception $e) {
                    Log::logAddLine('ERROR', $e->getMessage(), true);
                    Log::logAddLine('ERROR', $e->getTraceAsString(), isVerbose());
                }

                $pause = esc\Classes\Timer::getNextCyclePause();

                usleep($pause);
            } catch (\Maniaplanet\DedicatedServer\Xmlrpc\Exception $e) {
                Log::logAddLine('MPS', $e->getMessage());
            } catch (Error $e) {
                $errorClass = get_class($e);
                $output->writeln("<error>$errorClass in " . $e->getFile() . " on Line " . $e->getLine() . "</error>");
                $output->writeln("<fg=white;bg=red;options=bold>" . $e->getMessage() . "</>");
                $output->writeln("<error>===============================================================================</error>");
                $output->writeln("<error>" . $e->getTraceAsString() . "</error>");

                Log::logAddLine('CYCLE-ERROR', 'EvoSC encountered an error: ' . $e->getMessage(), false);
                Log::logAddLine('CYCLE-ERROR', $e->getTraceAsString(), false);
            }
        }
    }
}