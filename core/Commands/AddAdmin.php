<?php

namespace esc\Commands;

use esc\Classes\Database;
use esc\Classes\Log;
use esc\Controllers\ConfigController;
use esc\Models\Group;
use esc\Models\Player;
use Exception;
use Maniaplanet\DedicatedServer\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class AddAdmin extends Command
{
    protected function configure()
    {
        $this->setName('add:admin')
            ->setDescription('Adds player to AdminGroups')
            ->addArgument('login', InputArgument::REQUIRED, 'Player login to add')
            ->addArgument('groupId', InputArgument::OPTIONAL, 'Group id to add');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Log::setOutput($output);
        ConfigController::init();
        Database::init();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $login = $input->getArgument('login');
        $groupId = $input->getArgument('groupId');
        $helper = new QuestionHelper();


        $player = Player::find($login);

        if ($player === null) {
            $output->writeln("No player found with login '{$login}'.");
            exit(1);
        }
        if (!$groupId) {
            $table = new Table($output);
            $table->setHeaders(["Id", "Title"]);
            $groups = Group::all();

            foreach ($groups as $group) {
                $table->addRow(["<fg=green>{$group->id}</>", "{$group->Name}"]);
            }

            $table->render();

            $question = new Question("Enter group id where you wish to add '{$login}' (1)? ", 1);
            $groupId = $helper->ask($input, $output, $question);

        }

        if (is_numeric($groupId)) {
            $groupId = intval($groupId);
        } else {
            $output->writeln("Group id must be numeric and integer.");
            exit(1);
        }

        $groupName = Group::findOrFail($groupId)->Name;
        $player->group = $groupId;
        $player->save();
        $output->writeln("Successfully added '{$login}' to {$groupName} group.");

        try {
            $server = Connection::factory(
                config('server.ip'),
                config('server.port'),
                2,
                config('server.rpc.login'),
                config('server.rpc.password')
            );

            $server->chatSendServerMessage(
                "You have been added to group {$groupName}, please rejoin to this server.", $login);

        } catch (Exception $e) {
            // silent exception
        }
    }
}