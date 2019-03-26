<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixScores extends Command
{
    protected function configure()
    {
        $this->setName('fix:scores');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \esc\Classes\Log::setOutput($output);

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
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => $targetConfig->prefix,
        ]);

        $evoSC = $escCapsule->getConnection();

        $players = $evoSC->table('players')->get();
        $bar     = new \Symfony\Component\Console\Helper\ProgressBar($output, $players->count());
        $players->each(function ($player) use ($evoSC, $bar) {
            $score = $evoSC->table('local-records')->where('Player', $player->id)->selectRaw('100 - Rank as rank_diff')->get()->sum('rank_diff');

            $evoSC->table('stats')->where('Player', $player->id)->update([
                'Score' => $score,
            ]);

            $bar->advance();
        });

        $bar->finish();
        $output->writeln("\nFinished fixing scores.");

        $output->writeln("Finished ranks.");
        $ranked = $evoSC->table('stats')->where('Score', '>', 0)->orderByDesc('Score')->get();
        $bar    = new \Symfony\Component\Console\Helper\ProgressBar($output, $ranked->count());
        $ranked->each(function ($stat, $key) use ($evoSC, $bar) {
            $evoSC->table('stats')->where('Player', $stat->Player)->update(['Rank' => $key + 1]);
            $bar->advance();
        });
        $bar->finish();
        $output->writeln("\nFinished ranking players.");
    }
}
