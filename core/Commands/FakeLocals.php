<?php

namespace EvoSC\Commands;

use EvoSC\Classes\Database;
use EvoSC\Classes\DB;
use EvoSC\Classes\Log;
use EvoSC\Classes\Utility;
use EvoSC\Controllers\ConfigController;
use EvoSC\Models\Map;
use EvoSC\Modules\LocalRecords\LocalRecords;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FakeLocals extends Command
{
    /**
     * Command settings
     */
    protected function configure()
    {
        $this->setName('fake:locals')
            ->setDescription('Adds fake local records for testing.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        ConfigController::init();
        Log::setOutput($output);
        Database::init();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $maps = Map::all();
        $bar = new ProgressBar($output, $maps->count());
        $bar->start();

        foreach ($maps as $map) {
            $localCount = DB::table(LocalRecords::TABLE)->count();
            $local = DB::table(LocalRecords::TABLE)->where('Map', '=', $map->id)->first();

            for ($i = $localCount; $i < 100; $i++) {
                $playerId = DB::table('players')->inRandomOrder()->first()->id;
                DB::table(LocalRecords::TABLE)->insert([
                    'Player' => $playerId,
                    'Map' => $map->id,
                    'Score' => ($local->Score ?? rand(55000, 9000)) + rand(1, 100),
                    'Checkpoints' => $local->Checkpoints ?? '1,2,3,4,5,6,7',
                    'Rank' => 0
                ]);
            }

            $bar->advance();

            Utility::fixRanks('local-records', $map->id, config('locals.limit', 200));
        }

        $bar->finish();

        return 0;
    }
}