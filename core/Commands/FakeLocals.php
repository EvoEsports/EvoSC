<?php

namespace esc\Commands;

use esc\Classes\Database;
use esc\Classes\Log;
use esc\Controllers\ConfigController;
use esc\Models\Map;
use esc\Models\Player;
use esc\Modules\LocalRecords\LocalRecords;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FakeLocals extends Command
{
    protected function configure()
    {
        $this->setName('fake:locals')
            ->setDescription('Adds fake local records for testing.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        ConfigController::init();
        Log::setOutput($output);
        Database::init();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $maps = Map::all();
        $bar = new ProgressBar($output, $maps->count());
        $bar->start();

        foreach ($maps as $map) {
            $localCount = $map->locals()->count();
            $local = $map->locals()->first();

            for ($i = $localCount; $i < 100; $i++) {
                $playerId = Player::inRandomOrder()->first()->id;
                $map->locals()->insert([
                    'Player' => $playerId,
                    'Map' => $map->id,
                    'Score' => ($local->Score ?? rand(55000, 9000)) + rand(1, 100),
                    'Checkpoints' => $local->Checkpoints ?? '1,2,3,4,5,6,7',
                    'Rank' => 0
                ]);
            }

            $bar->advance();

            LocalRecords::fixRanks($map);
        }

        $bar->finish();
    }
}