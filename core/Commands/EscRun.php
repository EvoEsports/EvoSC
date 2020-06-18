<?php

namespace EvoSC\Commands;

use Error;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Database;
use EvoSC\Classes\DB;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Server;
use EvoSC\Classes\Timer;
use EvoSC\Controllers\AfkController;
use EvoSC\Controllers\BansController;
use EvoSC\Controllers\ChatController;
use EvoSC\Controllers\ConfigController;
use EvoSC\Controllers\ControllerController;
use EvoSC\Controllers\CountdownController;
use EvoSC\Controllers\EventController;
use EvoSC\Controllers\HookController;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\MatchSettingsController;
use EvoSC\Controllers\ModuleController;
use EvoSC\Controllers\PlanetsController;
use EvoSC\Controllers\PlayerController;
use EvoSC\Controllers\QueueController;
use EvoSC\Controllers\SetupController;
use EvoSC\Controllers\TemplateController;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\QuickButtons\QuickButtons;
use Exception;
use Spatie\Async\Pool;
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
            ->addOption('skip_migrate', 's', InputOption::VALUE_OPTIONAL, 'Skip migrations at start.', false)
            ->setDescription('Run Evo Server Controller');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        global $serverName;
        global $__ManiaPlanet;
        global $asyncPool;

        /*
        $asyncPool = Pool::create()
            ->concurrency(20)
            ->timeout(10)
            ->autoload(coreDir('../vendor/autoload.php'));
        */

        Log::setOutput($output);
        ConfigController::init();

        ChatCommand::removeAll();
        Timer::destroyAll();
        QuickButtons::removeAll();
        InputSetup::clearAll();

        if ($input->getOption('setup') !== false || !File::exists(cacheDir('.setupfinished'))) {
            SetupController::startSetup($input, $output, $this->getHelper('question'));
            return;
        }

        if ($input->getOption('skip_migrate') !== false) {
            $output->writeln('Skipping migrations.');
        } else {
            $migrate = $this->getApplication()->find('migrate');
            $migrate->execute($input, $output);
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

            $__ManiaPlanet = Server::getVersion()->name == 'ManiaPlanet';

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
        file_put_contents(baseDir(config('server.login') . '_evosc.pid'), getmypid());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        global $_onlinePlayers;
        global $serverName;

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
        DB::table('access-rights')->truncate();
        RestClient::init($serverName);
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

        ChatCommand::add('//restart-evosc', function () {
            restart_evosc();
        }, 'Restart EvoSC', 'ma');

        onlinePlayers()->each(function (Player $player) use ($_onlinePlayers) {
            $_onlinePlayers->put($player->Login, $player);
        });

        if (isVerbose()) {
            Log::write('Booting core finished.', true);
        }

        ModuleController::startModules(Server::getScriptName()['CurrentValue'], true);

        if (isVerbose()) {
            Log::write('Booting modules finished.', true);
        }

        $map = Map::where('filename', Server::getCurrentMapInfo()->fileName)->first();
        Hook::fire('BeginMap', $map);

        //Set connected players online
        $playerList = collect(Server::getPlayerList());

        foreach ($playerList as $maniaPlayer) {
            DB::table('players')->updateOrInsert(['Login' => $maniaPlayer->login], [
                'NickName' => $maniaPlayer->nickName,
            ]);
        }

        //Enable mode script rpc-callbacks else you wont get stuf flike checkpoints and finish
        Server::triggerModeScriptEventArray('XmlRpc.EnableCallbacks', ['true']);
        Server::disableServiceAnnounces(true);

        $failedConnectionRequests = 0;

        successMessage(secondary('EvoSC v' . getEscVersion()), ' started.')->setIcon('')->sendAll();

        //cycle-loop
        while (true) {
            try {
                Timer::startCycle();
                RestClient::curlTick();
                EventController::handleCallbacks(Server::executeCallbacks());

                $pause = Timer::getNextCyclePause();
                $failedConnectionRequests = 0;

                usleep($pause);
            } catch (Exception $e) {
                Log::write('Failed to fetch callbacks from dedicated-server. Failed attempts: ' . $failedConnectionRequests . '/50');
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
                $output->writeln("<error>$errorClass in " . $e->getFile() . " on Line " . $e->getLine() . "</error>");
                $output->writeln("<fg=white;bg=red;options=bold>" . $e->getMessage() . "</>");
                $output->writeln("<error>===============================================================================</error>");
                $output->writeln("<error>" . $e->getTraceAsString() . "</error>");

                Log::write('EvoSC encountered an error: ' . $e->getMessage(), false);
                Log::write($e->getTraceAsString(), false);
            }
        }
    }
}