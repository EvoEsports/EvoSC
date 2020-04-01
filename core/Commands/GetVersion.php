<?php

namespace esc\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetVersion extends Command
{
    protected function configure()
    {
        $this->setName('version')
            ->setDescription('Get the installed EvoSC version.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        printf("EvoSC-Version %s\n", getEscVersion());
    }
}