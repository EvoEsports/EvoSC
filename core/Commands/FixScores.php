<?php

namespace esc\Commands;

use esc\Classes\DB;
use esc\Classes\Log;
use esc\Controllers\ConfigController;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixScores extends Command
{
    protected function configure()
    {
        $this->setName('fix:scores');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Log::setOutput($output);
        ConfigController::init();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists('config/database.config.json')) {
            $output->writeln('config/database.config.json not found');

            return;
        }

        $targetConfig = json_decode(file_get_contents('config/database.config.json'));

        //Connect to esc database
        $escCapsule = new Capsule();
        $escCapsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $targetConfig->host,
            'database'  => $targetConfig->db,
            'username'  => $targetConfig->user,
            'password'  => $targetConfig->password,
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => $targetConfig->prefix,
        ]);

        $evoSC = $escCapsule->getConnection();

        $output->writeln("Updating player scores.");
        $playerIds = $evoSC->table('stats')->where('Locals', '>', 0)->pluck('Player')->toArray();
        $players = $evoSC->table('players')->whereIn('id', $playerIds)->get();
        $bar = new ProgressBar($output, $players->count());
        $limit = config('locals.limit');
        $players->each(function ($player) use ($evoSC, $bar, $limit) {
            $data = DB::table('local-records')
                ->join('players', 'local-records.Player', '=', 'players.id')
                ->join('maps', 'local-records.Map', '=', 'maps.id')
                ->selectRaw('Player as id, Login, SUM(Rank) as rank_sum, COUNT(Rank) as locals')
                ->where('maps.enabled', '=', 1)
                ->where('Login', $player->Login)
                ->groupBy('Login')
                ->get();


            foreach ($data as $stat) {
                $evoSC->table('stats')->where('Player', $player->id)->update([
                    'Score' => $limit * $stat->locals - intval($stat->rank_sum),
                ]);
            }

            $bar->advance();
        });

        $bar->finish();
        $output->writeln("\nFinished fixing scores.");

        $output->writeln("Fixing local ranks.");
        $evoSC->table('stats')->update(['Rank' => 9999]);
        $ranked = $evoSC->table('stats')->orderByDesc('Score')->where('Score', '>', 0)->get();
        $bar = new ProgressBar($output, $ranked->count());
        $ranked->each(function ($stat, $key) use ($evoSC, $bar) {
            $evoSC->table('stats')->where('Player', $stat->Player)->update(['Rank' => $key + 1]);
            $bar->advance();
        });
        $bar->finish();
        $output->writeln("\nFinished ranking players.");
    }
}
