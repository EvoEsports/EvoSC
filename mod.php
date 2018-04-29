<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunEsc extends Command
{
    protected function configure()
    {
        $this->setName('run');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Currently not available');
    }
}

class MakeMigration extends Command
{
    protected function configure()
    {
        $this->setName('make:migration')
            ->addArgument('migration_name', InputArgument::REQUIRED, 'The migration name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('migration_name');

        if (preg_match_all('/([A-Z][a-z]+)/', $name, $matches)) {
            $filename = 'Migrations/' . time() . '_' . str_slug(implode(' ', $matches[0])) . '.php';

            $template = str_replace('{className}', $name, '<?php

namespace esc\Migrations;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class {className} extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(\'table-name\', function (Blueprint $table) {
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
    public function down()
    {
        Schema::drop(\'table-name\');
    }
}');

            file_put_contents($filename, $template);
        }else{
            $output->writeln('Error: Invalid name entered, please use camel case (example: CreatePlayersTable)');
        }
    }
}

$application = new Application();

$application->add(new RunEsc());
$application->add(new MakeMigration());

try {
    $application->run();
} catch (\Exception $e) {
    die($e);
}