<?php

namespace EvoSC\Commands;

use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate extends Command
{
    /**
     * Command settings
     */
    protected function configure()
    {
        $this->setName('migrate')->setDescription('Run all database migrations. Run after pulling updates');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        global $_isVerbose;
        global $_isVeryVerbose;
        global $_isDebug;

        $_isVerbose = $output->isVerbose();
        $_isVeryVerbose = $output->isVeryVerbose();
        $_isDebug = $output->isDebug();

        $output->writeln('<fg=green>Executing migrations...</>');

        $config = json_decode(file_get_contents('config/database.config.json'));

        $capsule = new Capsule();

        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $config->host,
            'database' => $config->db,
            'username' => $config->user,
            'password' => $config->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => $config->prefix,
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $connection = $capsule->getConnection();

        $schemaBuilder = $connection->getSchemaBuilder();
        $schemaBuilder::defaultStringLength(191);

        try{
            if (!$schemaBuilder->hasTable('migrations')) {
                $output->writeln('Creating migrations table');
                $schemaBuilder->create('migrations', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('file')->unique();
                    $table->integer('batch');
                });
            }
        }catch(\Exception $e){
            Log::errorWithCause('Failed to create migrations table', $e);
            exit(1);
        }

        $previousBatch = $connection->table('migrations')
            ->get(['batch'])
            ->sortByDesc('batch')
            ->first();

        if ($previousBatch) {
            $batch = $previousBatch->batch + 1;
        } else {
            $batch = 1;
        }

        $migrations = $this->getMigrations();
        $migrationsTable = $connection->table('migrations');
        $executedMigrations = $migrationsTable->get(['file']);
        $anyMigrationRan = false;

        $migrations->each(function ($migration) use (
            $executedMigrations,
            $batch,
            $schemaBuilder,
            $migrationsTable,
            $output,
            &$anyMigrationRan
        ) {
            if ($executedMigrations->where('file', $migration->file)->isNotEmpty()) {
                //Skip already executed migrations
                return;
            }

            $content = file_get_contents($migration->path);

            if (preg_match('/class (.+) extends/', $content, $matches)) {
                $class = 'EvoSC\\Migrations\\' . $matches[1];
                $output->writeln('<fg=yellow>Migrating: ' . $migration->file . '</>');
                require_once $migration->path;
                $instance = new $class;
                $instance->up($schemaBuilder);

                $migrationsTable->insert(['file' => $migration->file, 'batch' => $batch]);
                $output->writeln('<fg=green>Migrated: ' . $migration->file . '</>');
                $anyMigrationRan = true;
            }
        });

        if(!$anyMigrationRan){
            $output->writeln('<fg=green>Nothing to migrate.</>');
        }

        if (!file_exists(cacheDir('.setupfinished'))) {
            File::put(cacheDir('.setupfinished'), 1);
        }

        return 0;
    }

    private function getMigrations(): Collection
    {
        $migrations = collect();

        $files = collect(scandir('Migrations'))->filter(function ($file) {
            return preg_match('/\.php$/', $file);
        })->filter(function ($file) {
            $content = file_get_contents('Migrations/' . $file);

            return preg_match('/extends Migration/', $content);
        })->map(function ($migration) {
            $col = collect();
            $col->path = "Migrations/$migration";
            $col->file = $migration;

            return $col;
        });

        $migrations = $migrations->merge($files);

        collect(scandir('core/Modules'))->filter(function ($moduleDir) {
            return is_dir("core/Modules/$moduleDir") && !in_array($moduleDir, ['.', '..']);
        })->filter(function ($moduleDir) {
            return is_dir("core/Modules/$moduleDir/Migrations");
        })->each(function ($moduleDir) use (&$migrations) {
            $moduleMigrations = collect(scandir("core/Modules/$moduleDir/Migrations"))->filter(function ($file) {
                return preg_match('/\.php$/', $file);
            })->filter(function ($file) use ($moduleDir) {
                $content = file_get_contents("core/Modules/$moduleDir/Migrations/" . $file);

                return preg_match('/extends Migration/', $content);
            })->map(function ($migration) use ($moduleDir) {
                $col = collect();
                $col->path = "core/Modules/$moduleDir/Migrations/$migration";
                $col->file = $migration;

                return $col;
            });

            $migrations = $migrations->merge($moduleMigrations);
        });

        return $migrations;
    }
}
