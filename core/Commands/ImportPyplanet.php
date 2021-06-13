<?php


namespace EvoSC\Commands;


use EvoSC\Classes\Database;
use EvoSC\Classes\DB;
use EvoSC\Classes\Log;
use EvoSC\Classes\Utility;
use EvoSC\Controllers\ConfigController;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportPyplanet extends Command
{
    /**
     * Command settings
     */
    protected function configure()
    {
        $this->setName('import:pyplanet')
            ->setDescription('Import data from pyplanet database, params: host, database, user, password [, table_prefix]')
            ->addArgument('host', InputArgument::REQUIRED, 'Host')
            ->addArgument('db', InputArgument::REQUIRED, 'Database')
            ->addArgument('user', InputArgument::REQUIRED, 'User')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addArgument('table_prefix', InputArgument::OPTIONAL, 'Prefix');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrate = $this->getApplication()->find('migrate');
        $migrate->execute($input, $output);

        $source = $input->getArguments();

        if (!file_exists('config/database.config.json')) {
            $output->writeln('config/database.config.json not found');

            return 1;
        }

        ConfigController::init();
        Log::setOutput($output);
        Database::init();

        //Connect to pyplanet database
        $pyplanetCapsule = new Capsule();
        $pyplanetCapsule->addConnection([
            'driver' => 'mysql',
            'host' => $source['host'],
            'database' => $source['db'],
            'username' => $source['user'],
            'password' => $source['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => $source['table_prefix'] ?? '',
        ]);
        $pyplanet = $pyplanetCapsule->getConnection();

        $output->writeln('<info>Transfering players.</>');
        $bar = new ProgressBar($output, $pyplanet->table('player')->count());
        $bar->start();

        foreach ($pyplanet->table('player')->get() as $player) {
            if (!Player::whereLogin($player->login)->exists()) {
                $group = 3;

                if ($player->level == 3) {
                    $group = 1;
                }
                if ($player->level == 2) {
                    $group = 2;
                }

                Player::insert([
                    'NickName' => $player->nickname,
                    'Login' => $player->login,
                    'last_visit' => $player->last_seen,
                    'Group' => $group
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        echo "\n";

        $output->writeln('<info>Transfering maps.</>');
        $bar = new ProgressBar($output, $pyplanet->table('map')->count());
        $bar->start();

        foreach ($pyplanet->table('map')->get() as $map) {
            if (!Map::whereUid($map->uid)->exists()) {
                $author = Player::whereLogin($map->author_login)->first();

                if (!$author) {
                    $authorId = Player::insertGetId([
                        'Login' => $map->author_login,
                        'NickName' => $map->author_nickname ?? $map->author_login
                    ]);
                } else {
                    $authorId = $author->id;
                }

                if (DB::table('maps')->where('filename', '=', $map->file)->exists()) {
                    DB::table('maps')->where('filename', '=', $map->file)->delete();
                }

                Map::insert([
                    'name' => $map->name,
                    'environment' => $map->environment,
                    'title_id' => $map->title,
                    'filename' => $map->file,
                    'uid' => $map->uid,
                    'author' => $authorId
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        echo "\n";


        $output->writeln('<info>Transfering map karmas.</>');

        $karma = $pyplanet->table('karma')
            ->join('map', 'karma.map_id', '=', 'map.id')
            ->join('player', 'karma.player_id', '=', 'player.id')
            ->get();

        $bar = new ProgressBar($output, $karma->count());
        $bar->start();

        foreach ($karma as $rating) {
            $player = Player::firstOrCreate([
                'Login' => $rating->login,
                'NickName' => $rating->nickname
            ]);

            $map = Map::whereUid($rating->uid)->first();

            if ($player && $map) {
                try {
                    DB::table('mx-karma')->insert([
                        'Player' => $player->id,
                        'Map' => $map->id,
                        'Rating' => ($rating->score == 1 ? 100 : 0)
                    ]);
                } catch (Exception $e) {
                    Log::errorWithCause("Failed to insert in mx-karma table", $e);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        echo "\n";


        $output->writeln('<info>Transfering local records.</>');

        $playerIds = $pyplanet->table('player')->pluck('login', 'id');
        $mapIds = collect();
        $locals = $pyplanet->table('localrecord')->get();
        $bar = new ProgressBar($output, $locals->count());
        $bar->start();

        foreach ($locals as $record) {
            if (!$mapIds->has($record->map_id)) {
                $uid = $pyplanet->table('map')->whereId($record->map_id)->first()->uid;
                $map = Map::whereUid($uid)->first();

                if ($map) {
                    $mapIds->put($record->map_id, $map->id);
                }
            }

            if ($mapIds->has($record->map_id)) {
                DB::table('local-records')->insert([
                    'Checkpoints' => $record->checkpoints,
                    'Score' => $record->score,
                    'Map' => $mapIds->get($record->map_id),
                    'Player' => Player::whereLogin($playerIds->get($record->player_id))->first()->id,
                    'Rank' => 100
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        echo "\n";
        $output->writeln('<info>Calculating local record ranks.</>');
        $bar = new ProgressBar($output, $mapIds->count());
        $bar->start();

        foreach ($mapIds as $id => $mappedId) {
            Utility::fixRanks('local-records', Map::find($mappedId)->id, config('locals.limit', 200));
            $bar->advance();
        }

        $bar->finish();
        echo "\n";

        $output->writeln('<info>Import finished.</>');

        return 0;
    }
}
