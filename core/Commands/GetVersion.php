<?php

namespace EvoSC\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetVersion extends Command
{
    /**
     * Command settings
     */
    protected function configure()
    {
        $this->setName('version')
            ->setDescription('Get the installed EvoSC version.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        printf("EvoSC-Version %s\n", getEvoSCVersion());

        return 0;
    }
}