<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

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
        $source = $input->getArguments();

        if (!file_exists('config/db.json')) {
            $output->writeln('config/db.json not found');
            return;
        }

        $targetConfig = json_decode(file_get_contents('config/db.json'));


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

        $esc->getSchemaBuilder()->dropAllTables();
        $migrationOutput = shell_exec('php mod.php migrate');
        $output->writeln($migrationOutput);


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
            'prefix'    => $source['table_prefix'] ?? ''
        ]);
        $uaseco = $uasecoCapsule->getConnection();


        //Import players & stats
        $output->writeln('Importing players & stats');
        $uasecoPlayers = $uaseco->table('players')->get();
        $bar           = $this->getBar($output, $uasecoPlayers->count());
        foreach ($uasecoPlayers as $player) {

            $ply = $esc->table('players')->whereLogin($player->Login)->first();

            if ($ply) {
                $plyId = $ply->id;
            } else {
                $plyId = $esc->table('players')->insertGetId([
                    'Login'    => $player->Login,
                    'NickName' => $player,
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
                'created_at' => $player->LastVisit
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

            $uasecoMap               = (array)$map;
            $uasecoMap['FileName']   = $map->Filename;
            $uasecoMap['UId']        = $map->Uid;
            $uasecoMap['LapRace']    = $map->MultiLap == 'true' ? 1 : 0;
            $uasecoMap['Author']     = $author->id;
            $uasecoMap['LastPlayed'] = \Carbon\Carbon::yesterday(); //Make it instantly available

            $data = collect($uasecoMap)->only(['FileName', 'UId', 'LapRace', 'Author', 'LastPlayed', 'Environment', 'NbCheckpoints', 'Mood', 'Name'])->toArray();

            $esc->table('maps')->insert($data);

            $bar->advance();
        }

        $bar->finish();
        $output->writeln("\n");


        //Import map ratings
        $output->writeln('Importing map-ratings');
        $ratings = $uaseco->table('ratings')->get();
        $bar = $this->getBar($output, $ratings->count());
        foreach ($ratings as $rating) {

            $mxKarma = [0, 20, 40, null, 60, 80, 100][$rating->Score + 3];
            $playerLogin = $uaseco->table('players')->where('PlayerId', $rating->PlayerId)->first()->Login;
            $player = $esc->table('players')->whereLogin($playerLogin)->first();

            $mapUid = $uaseco->table('maps')->where('MapId', $rating->MapId)->first()->Uid;
            $map = $esc->table('maps')->where('UId', $mapUid)->first();

            $esc->table('mx-karma')->insert([
                'Player' => $player->id,
                'Map' => $map->id,
                'rating' => $mxKarma
            ]);

            $bar->advance();
        }
        $bar->finish();
        $output->writeln("\n");


        //Import map ratings
        $output->writeln('Importing local records');
        $rankCount = 1000;
        $records = $uaseco->table('records')->get();
        $bar = $this->getBar($output, $records->count());
        foreach ($records as $record) {
            $playerLogin = $uaseco->table('players')->where('PlayerId', $record->PlayerId)->first()->Login;
            $player = $esc->table('players')->whereLogin($playerLogin)->first();

            $mapUid = $uaseco->table('maps')->where('MapId', $record->MapId)->first()->Uid;
            $map = $esc->table('maps')->where('UId', $mapUid)->first();

            $esc->table('local-records')->insert([
                'Map' => $map->id,
                'Player' => $player->id,
                'Score' => $record->Score,
                'Checkpoints' => $record->Checkpoints,
                'Rank' => $rankCount++
            ]);
            $bar->advance();
        }
        $bar->finish();
        $output->writeln("\n");


        //Fix local records ranks
        $output->writeln('Fixing local records ranks');
        $maps = $esc->table('maps')->get();
        $bar = $this->getBar($output, $esc->table('local-records')->count());
        foreach ($maps as $map) {
            $i = 1;
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