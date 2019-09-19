<?php

namespace esc\Commands;

use Error;
use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\Log;
use esc\Classes\Timer;
use esc\Controllers\AfkController;
use esc\Controllers\BansController;
use esc\Controllers\ChatController;
use esc\Controllers\ConfigController;
use esc\Controllers\ControllerController;
use esc\Controllers\CountdownController;
use esc\Controllers\EventController;
use esc\Controllers\HookController;
use esc\Controllers\MapController;
use esc\Controllers\MatchSettingsController;
use esc\Controllers\ModuleController;
use esc\Controllers\PlanetsController;
use esc\Controllers\PlayerController;
use esc\Controllers\QueueController;
use esc\Controllers\SetupController;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\Player;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EscRun extends Command
{
    protected function configure()
    {
        $this->setName('run')
            ->addOption('setup', null, InputOption::VALUE_OPTIONAL, 'Start the setup on boot.', false)
            ->addOption('skip_map_check', 'f', InputOption::VALUE_OPTIONAL, 'Start without verifying map integrity.',
                false)
            ->setDescription('Run Evo Server Controller');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        global $serverName;

        switch (pcntl_fork()) {
            case -1:
                $output->writeln('Starting char router failed.');
                break;

            case 0:
                $output->writeln('Starting chat router.');
                pcntl_exec('/usr/bin/php', ['esc', 'run:chat-router']);
                exit(0);

            default:
                //parent
        }

        Log::setOutput($output);
        ConfigController::init();

        if ($input->getOption('setup') !== false || !File::exists(cacheDir('.setupfinished'))) {
            SetupController::startSetup($input, $output, $this->getHelper('question'));
        }

        if ($input->getOption('skip_map_check') !== false) {
            global $_skipMapCheck;
            $_skipMapCheck = true;
        }

        try {
            $output->writeln("Connecting to server...");

            Server::init(
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
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $output->writeln("<error>Connecting to server failed: $msg</error>");
            exit(1);
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        file_put_contents(baseDir(config('server.login').'_evosc.pid'), getmypid());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrate = $this->getApplication()->find('migrate');
        $migrate->execute($input, $output);

        global $_onlinePlayers;
        global $_restart;
        $_restart = false;

        $version = getEscVersion();
        $motd = "      ______           _____ ______
     / ____/  _______ / ___// ____/
    / __/| | / / __ \\__ \/ /     
   / /___| |/ / /_/ /__/ / /___   
  /_____/|___/\____/____/\____/  $version
";

        $output->writeln("<fg=cyan;options=bold>$motd</>");

        Log::info("Starting...");

        Timer::setInterval(config('server.controller-interval') ?? 250);

        $_onlinePlayers = collect();

        Database::init();
        RestClient::init(serverName());
        HookController::init();
        TemplateController::init();
        ChatController::init();
        ManiaLinkEvent::init();
        QueueController::init();
        MatchSettingsController::init();
        MapController::init();
        PlayerController::init();
        AfkController::init();
        BansController::init();
        ModuleController::init();
        PlanetsController::init();
        CountdownController::init();
        ControllerController::loadControllers(Server::getScriptName()['CurrentValue'], true);

        //TODO: Collection Transform
        $logins = [];
        foreach (Server::getPlayerList(500, 0) as $player) {
            array_push($logins, $player->login);
        }
        Player::whereIn('Login', $logins)->get()->each(function (Player $player) use ($_onlinePlayers) {
            $_onlinePlayers->put($player->Login, $player);
        });

        if (isVerbose()) {
            Log::write('Booting core finished.', true);
        }

        ModuleController::startModules('TimeAttack.Script.txt');

        if (isVerbose()) {
            Log::write('Booting modules finished.', true);
        }

        $map = Map::where('filename', Server::getCurrentMapInfo()->fileName)->first();
        Hook::fire('BeginMap', $map);

        //Set connected players online
        $playerList = collect(Server::rpc()->getPlayerList());

        foreach ($playerList as $maniaPlayer) {
            Player::firstOrCreate(['Login' => $maniaPlayer->login], [
                'NickName' => $maniaPlayer->nickName,
            ]);
        }

        Server::cleanBlackList();
        Server::cleanBanList();

        //Enable mode script rpc-callbacks else you wont get stuf flike checkpoints and finish
        Server::triggerModeScriptEventArray('XmlRpc.EnableCallbacks', ['true']);
        Server::disableServiceAnnounces(true);

        $failedConnectionRequests = 0;

        infoMessage(secondary('EvoSC v'.getEscVersion()), ' started.')->sendAdmin();

        //cycle-loop
        while (true) {
            try {
                Timer::startCycle();

                EventController::handleCallbacks(Server::executeCallbacks());

                $pause = Timer::getNextCyclePause();
                $failedConnectionRequests = 0;

                if ($_restart) {
                    return;
                }

                usleep($pause);
            } catch (Exception $e) {
                Log::write('Failed to fetch callbacks from dedicated-server. Failed attempts: '.$failedConnectionRequests.'/50');
                Log::write($e->getMessage());

                $failedConnectionRequests++;
                if ($failedConnectionRequests > 50) {
                    Log::write('MPS',
                        sprintf('Connection terminated after %d connection-failures.', $failedConnectionRequests));

                    return;
                }
                sleep(5);
            } catch (Error $e) {
                $errorClass = get_class($e);
                $output->writeln("<error>$errorClass in ".$e->getFile()." on Line ".$e->getLine()."</error>");
                $output->writeln("<fg=white;bg=red;options=bold>".$e->getMessage()."</>");
                $output->writeln("<error>===============================================================================</error>");
                $output->writeln("<error>".$e->getTraceAsString()."</error>");

                Log::write('EvoSC encountered an error: '.$e->getMessage(), false);
                Log::write($e->getTraceAsString(), false);
            }
        }
    }

//    private function restart(InputInterface $input, OutputInterface $output)
//    {
//        $output->writeln('<bg=cyan>Restarting</>');
//
//        Timer::destroyAll();
//        HookController::init();
//
//        $this->initialize($input, $output);
//        $this->execute($input, $output);
//    }
}