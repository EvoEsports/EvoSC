<?php

namespace EvoSC\Commands;

use EvoSC\Classes\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteUnusedConfigs extends Command
{
    /**
     * Command settings
     */
    protected function configure()
    {
        $this->setName('configs:clean')
            ->setDescription('Removes stale configs that are no more used by EvoSC core.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Log::setOutput($output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outdated = [
            'match-tracker.config.json',
            'evosc.config.json'
        ];

        $output->writeln("Deleting stale configs...");

        foreach ($outdated as $outdatedConfigFile) {
            $path = configDir($outdatedConfigFile);

            if (file_exists($path)) {
                $output->writeln("Deleting config file $outdatedConfigFile.");
                unlink($path);
            }
        }

        return 0;
    }
}
