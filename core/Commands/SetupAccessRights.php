<?php

namespace EvoSC\Commands;

use EvoSC\Classes\Database;
use EvoSC\Classes\Log;
use EvoSC\Controllers\ConfigController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupAccessRights extends Command
{
    protected function configure()
    {
        $this->setName('setup:access-rights')
            ->setDescription('Lets you choose the access-rights for your groups.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Log::setOutput($output);
        ConfigController::init();
        Database::init();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }
}