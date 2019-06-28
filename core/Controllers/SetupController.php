<?php

namespace esc\Controllers;


use esc\Classes\File;

use Migrate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class SetupController
{
    /**
     * @var InputInterface
     */
    private static $input;

    /**
     * @var OutputInterface
     */
    private static $output;

    /**
     * @var QuestionHelper
     */
    private static $helper;

    public static function startSetup(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        self::$input = $input;
        self::$output = $output;
        self::$helper = $helper;

        self::printInfo('<fg=cyan>Starting EvoSC Setup.</>');

        //Check that cache directory exists
        if (!is_dir(cacheDir())) {
            mkdir(cacheDir());
        }

        //Check that logs directory exists
        if (!is_dir(logDir())) {
            mkdir(logDir());
        }

        self::doServerConfig();
        self::doDatabaseConfig();
        self::doDedimaniaConfig();
        self::doMxKarmaConfig();
        self::doMusicConfig();


        File::put(cacheDir('.setupfinished'), 1);
    }

    private static function doServerConfig()
    {
        $questions = [
            [
                'id'       => 'login',
                'question' => 'Enter your server login',
                'default'  => '',
            ],
            [
                'id'       => 'ip',
                'question' => 'Enter your server ip',
                'default'  => 'localhost',
            ],
            [
                'id'       => 'port',
                'question' => 'Enter the RPC port',
                'default'  => 5000,
            ],
            [
                'id'       => 'rpc.login',
                'question' => 'Enter the RPC login',
                'default'  => 'SuperAdmin',
            ],
            [
                'id'       => 'rpc.password',
                'question' => 'Enter the RPC password',
                'default'  => 'SuperAdmin',
            ],
            [
                'id'       => 'default-matchsettings',
                'question' => 'Enter the default match-settings filename',
                'default'  => 'maplist.txt',
            ],
        ];

        self::askBatch('server', $questions);
        self::printInfo('Configuration of server.config.json finished.');
    }

    private static function doDatabaseConfig()
    {
        $questions = [
            [
                'id'       => 'host',
                'question' => 'Enter the database host (can you add port with :3307)',
                'default'  => 'localhost',
            ],
            [
                'id'       => 'db',
                'question' => 'Enter the name of the database',
                'default'  => '',
            ],
            [
                'id'       => 'user',
                'question' => 'Enter the database-users name',
                'default'  => '',
            ],
            [
                'id'       => 'password',
                'question' => 'Enter the database-users password',
                'default'  => '',
            ],
        ];

        self::askBatch('database', $questions);
        self::printInfo('Configuration of database.config.json finished.');
        self::printInfo('Running database migrate.');

        $application = new Application();
        $application->add(new Migrate());
        $application->setDefaultCommand("migrate", true);
        try {
            $application->run();
        } catch (\Exception $e) {
            self::printError($e->getMessage());
        }
    }

    private static function doDedimaniaConfig()
    {
        $question = new ConfirmationQuestion('<fg=green>Configure dedimania? [</><fg=yellow>y/n</><fg=green>]:</> ',
            true);

        if (!self::$helper->ask(self::$input, self::$output, $question)) {
            return;
        }

        $questions = [
            [
                'id'       => 'login',
                'question' => 'Enter your dedimania login',
                'default'  => '',
            ],
            [
                'id'       => 'key',
                'question' => 'Enter your dedimania key',
                'default'  => '',
            ],
        ];

        self::askBatch('dedimania', $questions);
        self::printInfo('Configuration of dedimania.config.json finished.');

        ConfigController::saveConfig('dedimania.enabled', true);
    }

    private static function doMxKarmaConfig()
    {
        $question = new ConfirmationQuestion('<fg=green>Configure ManiaExchange-Karma? [</><fg=yellow>y/n</><fg=green>]:</> ',
            true);

        if (!self::$helper->ask(self::$input, self::$output, $question)) {
            return;
        }

        $questions = [
            [
                'id'       => 'key',
                'question' => 'Enter your mx-karma key',
                'default'  => '',
            ],
        ];

        self::askBatch('mx-karma', $questions);

        ConfigController::saveConfig('mx-karma.enabled', true);
        self::printInfo('Configuration of mx-karma.config.json finished.');
    }

    private static function doMusicConfig()
    {
        $question = new ConfirmationQuestion('<fg=green>Configure music server url? [</><fg=yellow>y/n</><fg=green>]:</> ',
            true);

        if (!self::$helper->ask(self::$input, self::$output, $question)) {
            return;
        }

        $questions = [
            [
                'id'       => 'url',
                'question' => 'Enter your music server url',
                'default'  => '',
            ],
        ];

        self::askBatch('music', $questions);
        self::printInfo('Configuration of music.config.json finished.');

        ConfigController::saveConfig('music.enabled', true);
    }

    private static function printError(string $text)
    {
        self::$output->writeln("<error>$text</error>");
    }

    private static function printInfo(string $text)
    {
        self::$output->writeln("<fg=cyan>$text</>");
    }

    private static function askEnter(string $questionString, string $default = '', bool $optional = false)
    {
        $question = new Question('<fg=green>' . $questionString . (empty($default) ? ": " : "[$default]: ") . '</>',
            $default);
        $answer = self::$helper->ask(self::$input, self::$output, $question);

        if (!$answer) {
            if (!empty($default) || $optional) {
                $answer = $default;
            }
        }

        return $answer;
    }

    private static function askBatch(string $file, array $questions)
    {
        foreach ($questions as $key => $questionData) {
            $id = $file . '.' . $questionData['id'];
            $question = $questionData['question'];
            $default = $questionData['default'];
            $optional = array_key_exists('optional', $questionData);

            if (!config($id)) {
                while (true) {
                    $required = 'string';
                    $value = self::askEnter(sprintf("\033[1m[%s %d/%d]\033[0m %s", $file, $key + 1, count($questions),
                        $question), $default, $optional);

                    if (is_int($default)) {
                        $value = intval($value);
                        $required = 'integer';
                    }

                    if (is_float($default)) {
                        $value = floatval($value);
                        $required = 'float';
                    }

                    if (is_bool($default)) {
                        $value = boolval($value);
                        $required = 'boolean';
                    }

                    if (empty($value)) {
                        self::printError('Value can not be empty and needs to be of type "' . $required . '"');
                        continue;
                    }

                    break;
                }

                ConfigController::saveConfig($id, $value);
            }
        }
    }
}