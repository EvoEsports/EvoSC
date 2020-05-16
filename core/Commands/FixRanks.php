<?php

namespace EvoSC\Commands;

use EvoSC\Classes\Database;
use EvoSC\Classes\DB;
use EvoSC\Classes\Log;
use EvoSC\Controllers\ConfigController;
use EvoSC\Models\Map;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixRanks extends Command
{
    protected function configure()
    {
        $this->setName('fix:local-ranks')
            ->setDescription('Recalculate all ranks for the local records.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        ConfigController::init();
        Log::setOutput($output);
        Database::init();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $maps = Map::whereEnabled(1)->get();
        $bar = new ProgressBar($output, $maps->count());
        $bar->start();

        foreach ($maps as $map) {
            $this->fixRanks($map);
            $bar->advance();
        }

        $bar->finish();
    }

    private function fixRanks(Map $map)
    {
        DB::raw('SET @rank=0');
        DB::raw('UPDATE `local-records` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = ' . $map->id . ' ORDER BY `Score`');
    }
}