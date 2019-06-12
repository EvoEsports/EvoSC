<?php

require 'autoload.php';
require 'global-functions.php';

use esc\Classes\Server;
use esc\Classes\Log;
use esc\Classes\Timer;
use esc\Controllers\EventController;
use esc\Models\Map;
use esc\Models\Player;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EscRun extends Command
{
    protected function configure()
    {
        $this->setName('run')
             ->setDescription('Run Evo Server Controller');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        global $escVersion;
        global $serverName;
        global $_isVerbose;
        global $_isVeryVerbose;
        global $_isDebug;

        $_isVerbose     = $output->isVerbose();
        $_isVeryVerbose = $output->isVeryVerbose();
        $_isDebug       = $output->isDebug();

        $escVersion = '0.68.26';

        Log::setOutput($output);
        esc\Controllers\ConfigController::init();
        esc\Controllers\SetupController::startSetup($input, $output, $this->getHelper('question'));

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

            $serverName = Server::getServerName();

            if (!Server::isAutoSaveValidationReplaysEnabled()) {
                Server::autoSaveValidationReplays(true);
            }
            if (!Server::isAutoSaveReplaysEnabled()) {
                Server::autoSaveReplays(true);
            }

            //Disable all default ManiaPlanet votes
            /*
            $voteRatio = new \Maniaplanet\DedicatedServer\Structures\VoteRatio(\Maniaplanet\DedicatedServer\Structures\VoteRatio::COMMAND_DEFAULT, -1.0);
            Server::setCallVoteRatios([$voteRatio]);
            */
            Server::setCallVoteTimeOut(0);

            $output->writeln("Connection established.");
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $output->writeln("<error>Connecting to server failed: $msg</error>");
            exit(1);
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        file_put_contents(baseDir(config('server.login') . '_evosc.pid'), getmypid());
    }

    private function migrate(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->getApplication()->find('migrate')->run($input, $output);
        } catch (Exception $e) {
            Log::error('Failed to migrate.');
            exit(5);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->migrate($input, $output);

        global $_onlinePlayers;

        $version = getEscVersion();
        $motd    = "      ______           _____ ______
     / ____/  _______ / ___// ____/
    / __/| | / / __ \\__ \/ /     
   / /___| |/ / /_/ /__/ / /___   
  /_____/|___/\____/____/\____/  $version
";

        $output->writeln("<fg=cyan;options=bold>$motd</>");

        Log::info("Starting...");

        Timer::setInterval(config('server.controller-interval') ?? 250);

        $_onlinePlayers = collect();

        esc\Classes\Database::init();
        esc\Classes\RestClient::init(serverName());
        esc\Controllers\HookController::init();
        esc\Controllers\TemplateController::init();
        esc\Controllers\ChatController::init();
        esc\Classes\ManiaLinkEvent::init();
        esc\Controllers\QueueController::init();
        esc\Controllers\MatchSettingsController::init();
        esc\Controllers\MapController::init();
        // esc\Controllers\AfkController::init();
        esc\Controllers\PlayerController::init();
        esc\Controllers\BansController::init();
        esc\Controllers\ModuleController::init();
        esc\Controllers\PlanetsController::init();
        esc\Controllers\CountdownController::init();

        $logins = [];
        foreach (Server::getPlayerList(500, 0) as $player) {
            array_push($logins, $player->login);
        }
        Player::whereIn('Login', $logins)->get()->each(function (Player $player) use ($_onlinePlayers) {
            $_onlinePlayers->put($player->Login, $player);
        });

        if (isVerbose()) {
            Log::logAddLine('BOOT', 'Booting core finished.', true);
        }

        esc\Controllers\ModuleController::bootModules();

        if (isVerbose()) {
            Log::logAddLine('BOOT', 'Booting modules finished.', true);
        }

        $map = Map::where('filename', esc\Classes\Server::getCurrentMapInfo()->fileName)->first();
        esc\Classes\Hook::fire('BeginMap', $map);

        //Set connected players online
        $playerList = collect(Server::rpc()->getPlayerList());

        foreach ($playerList as $maniaPlayer) {
            Player::firstOrCreate(['Login' => $maniaPlayer->login], [
                'NickName' => $maniaPlayer->nickName,
            ]);
        }

        //Enable mode script rpc-callbacks else you wont get stuf flike checkpoints and finish
        Server::triggerModeScriptEventArray('XmlRpc.EnableCallbacks', ['true']);
        Server::disableServiceAnnounces(true);

        $failedConnectionRequests = 0;

        //cycle-loop
        while (true) {
            try {
                esc\Classes\Timer::startCycle();

                EventController::handleCallbacks(esc\Classes\Server::executeCallbacks());

                $pause                    = esc\Classes\Timer::getNextCyclePause();
                $failedConnectionRequests = 0;

                usleep($pause);
            } catch (\Exception $e) {
                Log::logAddLine('MPS', 'Failed to fetch callbacks from dedicated-server. Failed attempts: ' . $failedConnectionRequests . '/50');
                Log::logAddLine('MPS', $e->getMessage());

                $failedConnectionRequests++;
                if ($failedConnectionRequests > 50) {
                    Log::logAddLine('MPS', sprintf('Connection terminated after %d connection-failures.', $failedConnectionRequests));

                    return;
                }
                sleep(5);
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