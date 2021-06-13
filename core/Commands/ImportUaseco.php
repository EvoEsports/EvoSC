<?php

namespace EvoSC\Commands;

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportUaseco extends Command
{
    /**
     * Command settings
     */
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

    /**
     * @param $output
     * @param $count
     *
     * @return ProgressBar
     */
    private function getBar($output, $count)
    {
        $bar = new ProgressBar($output, $count);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
        $bar->start();

        return $bar;
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

        $targetConfig = json_decode(file_get_contents('config/database.config.json'));

        //Connect to esc database
        $escCapsule = new Capsule();
        $escCapsule->addConnection([
            'driver' => 'mysql',
            'host' => $targetConfig->host,
            'database' => $targetConfig->db,
            'username' => $targetConfig->user,
            'password' => $targetConfig->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => $targetConfig->prefix,
        ]);
        $esc = $escCapsule->getConnection();

        //Connect to uaseco database
        $uasecoCapsule = new Capsule();
        $uasecoCapsule->addConnection([
            'driver' => 'mysql',
            'host' => $source['host'],
            'database' => $source['db'],
            'username' => $source['user'],
            'password' => $source['password'],
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => $source['table_prefix'] ?? '',
        ]);
        $uaseco = $uasecoCapsule->getConnection();


        //Import players & stats
        $output->writeln('Importing players & stats');
        $uasecoPlayers = $uaseco->table('players')->get();
        $bar = $this->getBar($output, $uasecoPlayers->count());
        foreach ($uasecoPlayers as $player) {
            $ply = $esc->table('players')->where('Login', '=', $player->Login)->first();

            if ($ply) {
                $plyId = $ply->id;
                $esc->table('players')->where('id', '=', $plyId)->update([
                    'path' => $player->Zone,
                    'NickName' => $player->Nickname,
                    'last_visit' => $player->LastVisit,
                ]);
            } else {
                $plyId = $esc->table('players')->insertGetId([
                    'Login' => $player->Login,
                    'path' => $player->Zone,
                    'NickName' => $player->Nickname,
                    'last_visit' => $player->LastVisit,
                ]);
            }

            $esc->table('stats')->updateOrInsert([
                'Player' => $plyId
            ], [
                'Visits' => $player->Visits,
                'Wins' => $player->Wins,
                'Donations' => $player->Donations,
                'Playtime' => floor($player->TimePlayed / 60),
                'Finishes' => $player->MostFinished,
                'Locals' => $player->MostRecords,
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
        $bar = $this->getBar($output, $authors->count());
        foreach ($authors as $author) {
            if (!$esc->table('players')->where('Login', $author->Login)->exists()) {
                $esc->table('players')->insert([
                    'Login' => $author->Login,
                    'NickName' => $author->Nickname,
                    'path' => $author->Zone,
                ]);
            }

            $bar->advance();
        }
        $bar->finish();
        $output->writeln("\n");


        //Import maps
        $output->writeln('Importing maps');
        $maps = $uaseco->table('maps')->get();
        $bar = $this->getBar($output, $maps->count());
        foreach ($maps as $map) {
            $authorLogin = $uaseco->table('authors')->where('AuthorId', $map->AuthorId)->first()->Login;
            $author = $esc->table('players')->where('Login', '=', $authorLogin)->first();

            if ($esc->table('maps')->where('uid', $map->Uid)->exists()) {
                //TODO: Handle multiple map versions
                continue;
            }

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
        $bar = $this->getBar($output, $ratings->count());
        foreach ($ratings as $rating) {
            $mxKarma = [0, 20, 40, 50, 60, 80, 100][$rating->Score + 3];
            $playerLogin = $uaseco->table('players')->where('PlayerId', '=', $rating->PlayerId)->first()->Login;
            $player = $esc->table('players')->where('Login', '=', $playerLogin)->first();

            $mapUid = $uaseco->table('maps')->where('MapId', '=', $rating->MapId)->first()->Uid;
            $map = $esc->table('maps')->where('UId', '=', $mapUid)->first();

            $esc->table('mx-karma')->insert([
                'Player' => $player->id,
                'Map' => $map->id,
                'rating' => $mxKarma,
            ]);

            $bar->advance();
        }
        $bar->finish();
        $output->writeln("\n");

        //Import local records
        $output->writeln('Importing local records: Preparing records...');
        $playerIdMap = collect();

        $output->writeln('Importing local records: Preparing UASECO player mapping...');
        $uasecoMap = $uaseco->table('players')->select(['PlayerId', 'Login'])->get()->pluck('Login', 'PlayerId');
        $playerTable = $uaseco->table('records')->select('PlayerId')->distinct()->get()->pluck('PlayerId');
        $bar = $this->getBar($output, $playerTable->count());
        $playerTable->map(function ($uasecoPlayerId) use ($esc, $uasecoMap, $bar, &$playerIdMap) {
            $playerLogin = $uasecoMap->get($uasecoPlayerId);
            $evoscPlayerId = $esc->table('players')->where('Login', '=', $playerLogin)->select('id')->first()->id;
            $playerIdMap->put($uasecoPlayerId, $evoscPlayerId);
            $bar->advance();
        });
        $bar->finish();
        $output->writeln("\n");

        $output->writeln('Importing local records: Starting import...');
        $uasecoMapMap = $uaseco->table('maps')->select(['MapId', 'Uid'])->get()->pluck('Uid', 'MapId');
        $bar = $this->getBar($output, $uaseco->table('records')->count());
        $uasecoMapMap->each(function ($uid, $uasecoMapId) use ($uaseco, $esc, $playerIdMap, $bar) {
            $map = $esc->table('maps')->where('uid', '=', $uid)->first();
            $mapId = $map->id;

            $evoscRecords = $esc->table('local-records')->where('Map', '=', $mapId)->get()->keyBy('Player');
            $uasecoRecords = $uaseco->table('records')->where('MapId', '=', $uasecoMapId)->get();

            $uasecoRecords->each(function ($record) use ($playerIdMap, $evoscRecords, $esc, $mapId, $bar) {
                $evoscPlayerId = $playerIdMap->get($record->PlayerId);

                if ($evoscRecords->has($evoscPlayerId)) {
                    $existingRecord = $evoscRecords->get($evoscPlayerId);

                    if ($existingRecord->Score <= $record->Score) {
                        $bar->advance();

                        return;
                    }

                    $esc->table('local-records')->where('Map', '=', $mapId)->where('Player', '=',
                        $evoscPlayerId)->update([
                        'Score' => $record->Score,
                        'Checkpoints' => $record->Checkpoints,
                        'Rank' => -1,
                    ]);
                } else {
                    $esc->table('local-records')->insert([
                        'Player' => $evoscPlayerId,
                        'Map' => $mapId,
                        'Score' => $record->Score,
                        'Checkpoints' => $record->Checkpoints,
                        'Rank' => -1,
                    ]);
                }

                $bar->advance();
            });
        });

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
                $esc->table('local-records')->where('id', '=', $local->id)->update(['Rank' => $i]);
                $i++;
                $bar->advance();
            }
        }
        $bar->finish();
        $output->writeln("\n");


        return 0;
    }
}
