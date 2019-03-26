<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportUaseco extends Command
{
    protected function configure()
    {
        $this->setName('import:uaseco')
             ->setDescription('Import data from uaseco database, params: host, database, user, password [, table_prefix]')
             ->addArgument('host', InputArgument::REQUIRED, 'Host')
             ->addArgument('db', InputArgument::REQUIRED, 'Database')
             ->addArgument('user', InputArgument::REQUIRED, 'User')
             ->addArgument('password', InputArgument::REQUIRED, 'Password')
             ->addArgument('table_prefix', InputArgument::OPTIONAL, 'Prefix');
    }

    private function getBar($output, $count)
    {
        $bar = new \Symfony\Component\Console\Helper\ProgressBar($output, $count);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
        $bar->start();

        return $bar;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \esc\Classes\Log::setOutput($output);

        $source = $input->getArguments();

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
        $esc = $escCapsule->getConnection();

        /*
        $esc->getSchemaBuilder()->dropAllTables();
        $migrationOutput = shell_exec('php mod.php migrate');
        $output->writeln($migrationOutput);
        */

        //Connect to uaseco database
        $uasecoCapsule = new Capsule();
        $uasecoCapsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $source['host'],
            'database'  => $source['db'],
            'username'  => $source['user'],
            'password'  => $source['password'],
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => $source['table_prefix'] ?? '',
        ]);
        $uaseco = $uasecoCapsule->getConnection();


        /*
        //Import players & stats
        $output->writeln('Importing players & stats');
        $uasecoPlayers = $uaseco->table('players')->get();
        $bar           = $this->getBar($output, $uasecoPlayers->count());
        foreach ($uasecoPlayers as $player) {
            $ply = $esc->table('players')->whereLogin($player->Login)->first();

            if ($ply) {
                $plyId = $ply->id;
                $ply->update([
                    'path'       => $player->Zone,
                    'NickName'   => $player->Nickname,
                    'last_visit' => $player->LastVisit,
                ]);
            } else {
                $plyId = $esc->table('players')->insertGetId([
                    'Login'      => $player->Login,
                    'path'       => $player->Zone,
                    'NickName'   => $player->Nickname,
                    'last_visit' => $player->LastVisit,
                ]);
            }

            $esc->table('stats')->updateOrInsert([
                'Player'     => $plyId,
                'Visits'     => $player->Visits,
                'Wins'       => $player->Wins,
                'Donations'  => $player->Donations,
                'Playtime'   => floor($player->TimePlayed / 60),
                'Finishes'   => $player->MostFinished,
                'Locals'     => $player->MostRecords,
                'updated_at' => $player->LastVisit,
                'created_at' => $player->LastVisit,
            ]);

            $bar->advance();
        }
        $bar->finish();
        $output->writeln("\n");


        //Import authors
        $output->writeln('Importing map authors');
        $authors = $uaseco->table('authors')->get();
        $bar     = $this->getBar($output, $authors->count());
        foreach ($authors as $author) {
            if ($esc->table('players')->where('Login', $author->Login)->get()->isEmpty()) {
                $esc->table('players')->insert([
                    'Login'    => $author->Login,
                    'NickName' => $author->Nickname,
                    'path'     => $author->Zone,
                ]);
            }

            $bar->advance();
        }
        $bar->finish();
        $output->writeln("\n");


        //Import maps
        $output->writeln('Importing maps');
        $maps = $uaseco->table('maps')->get();
        $bar  = $this->getBar($output, $maps->count());
        foreach ($maps as $map) {
            $authorLogin = $uaseco->table('authors')->where('AuthorId', $map->AuthorId)->first()->Login;
            $author      = $esc->table('players')->whereLogin($authorLogin)->first();

            $esc->table('maps')->insert([
                'filename' => $map->Filename,
                'author' => $author->id,
                'uid' => $map->Uid,
            ]);

            $bar->advance();
        }

        $bar->finish();
        $output->writeln("\n");


        //Import map ratings
        $output->writeln('Importing map-ratings');
        $ratings = $uaseco->table('ratings')->get();
        $bar     = $this->getBar($output, $ratings->count());
        foreach ($ratings as $rating) {
            $mxKarma     = [0, 20, 40, 50, 60, 80, 100][$rating->Score + 3];
            $playerLogin = $uaseco->table('players')->where('PlayerId', $rating->PlayerId)->first()->Login;
            $player      = $esc->table('players')->whereLogin($playerLogin)->first();

            $mapUid = $uaseco->table('maps')->where('MapId', $rating->MapId)->first()->Uid;
            $map    = $esc->table('maps')->where('UId', $mapUid)->first();

            $esc->table('mx-karma')->insert([
                'Player' => $player->id,
                'Map'    => $map->id,
                'rating' => $mxKarma,
            ]);

            $bar->advance();
        }
        $bar->finish();
        $output->writeln("\n");
        */


        //Import local records
        $players = $esc->table('players')->get()->pluck('id', 'Login');
        $maps    = $esc->table('maps')->get()->pluck('id', 'uid');

        $output->writeln('Importing local records');
        $records = $uaseco->table('records')->groupBy('MapId');

        $playerIdMap = collect();
        $output->writeln('Importing local records: Preparing uaseco player mapping.');
        $playerTable = $uaseco->table('records')->select('PlayerId')->distinct()->get();
        $bar         = $this->getBar($output, $playerTable->count());
        $playerTable->map(function ($uasecoPlayerId) use ($esc, $bar, &$playerIdMap) {
            $evoscPlayerId = $esc->table('players')->where('Login', $uasecoPlayerId)->first()->id;
            $playerIdMap->put($uasecoPlayerId, $evoscPlayerId);
            $bar->advance();
        });
        $bar->finish();

        var_dump($records);
        die();

        $rankCount = 1000;
        $records   = $uaseco->table('records')->get();
        $bar       = $this->getBar($output, $records->count());
        foreach ($records as $record) {
            $playerLogin = $uaseco->table('players')->where('PlayerId', $record->PlayerId)->first()->Login;
            $playerId    = $players->get($playerLogin);

            $mapUid = $uaseco->table('maps')->where('MapId', $record->MapId)->first()->Uid;
            $mapId  = $maps->get($mapUid);

            $existingRecord = $esc->table('local-records')->where('Map', $mapId)->where('Player', $playerId)->first();

            if ($existingRecord && $existingRecord->Score <= $record->Score) {
                $bar->advance();
                continue;
            }

            $esc->table('local-records')->updateOrInsert(['Player' => $playerId],
                [
                    'Map'         => $mapId,
                    'Score'       => $record->Score,
                    'Checkpoints' => $record->Checkpoints,
                    'Rank'        => $rankCount++,
                ]);

            $bar->advance();
        }
        $bar->finish();
        $output->writeln("\n");


        //Fix local records ranks
        $output->writeln('Fixing local records ranks');
        $maps = $esc->table('maps')->get();
        $bar  = $this->getBar($output, $esc->table('local-records')->count());
        foreach ($maps as $map) {
            $i      = 1;
            $locals = $esc->table('local-records')->whereMap($map->id)->orderBy('Score')->get();
            foreach ($locals as $local) {
                $esc->table('local-records')->whereId($local->id)->update(['Rank' => $i]);
                $i++;
                $bar->advance();
            }
        }
        $bar->finish();
        $output->writeln("\n");
    }
}