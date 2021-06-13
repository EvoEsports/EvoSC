<?php

namespace EvoSC\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMigration extends Command
{
    /**
     * Command settings
     */
    protected function configure()
    {
        $this->setName('make:migration')
            ->setDescription('Create migration, placed in /Migrations')
            ->addArgument('migration_name', InputArgument::REQUIRED, 'The migration name');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('migration_name');

        if (preg_match_all('/([A-Z][a-z]+)/', $name, $matches)) {
            $filename = 'Migrations/' . time() . '_' . Str::slug(implode(' ', $matches[0])) . '.php';

            $template = str_replace('{className}', $name, '<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class {className} extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create(\'table-name\', function (Blueprint $table) {
            $table->increments(\'id\');
            $table->string(\'column1\');
            $table->integer(\'column2\')->nullable();
            $table->boolean(\'column3\')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->dropIfExists(\'table-name\');
    }
}');

            file_put_contents($filename, $template);
        } else {
            $output->writeln('Error: Invalid name entered, please use camel case (example: CreatePlayersTable)');
        }

        return 0;
    }
}
