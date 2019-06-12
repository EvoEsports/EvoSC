<?php


namespace esc\Controllers;


use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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

    private static $startMessageShown = false;

    public static function startSetup(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        self::$input  = $input;
        self::$output = $output;
        self::$helper = $helper;

        self::doServerConfig();
        self::doDatabaseConfig();
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
                'default'  => '',
            ],
            [
                'id'       => 'rpc.password',
                'question' => 'Enter the RPC password',
                'default'  => '',
            ],
            [
                'id'       => 'default-matchsettings',
                'question' => 'Enter the default match-settings filename',
                'default'  => '',
            ],
        ];

        self::askBatch('server', $questions);
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
    }

    private static function printError(string $text)
    {
        self::$output->writeln("<error>$text</error>");
    }

    private static function askEnter(string $questionString, string $default = '', bool $optional = false)
    {
        $question = new Question('<fg=green>' . $questionString . (empty($default) ? ": " : "[$default]: ") . '</>', $default);
        $answer   = self::$helper->ask(self::$input, self::$output, $question);

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
            $id       = $file . '.' . $questionData['id'];
            $question = $questionData['question'];
            $default  = $questionData['default'];
            $optional = array_key_exists('optional', $questionData);

            if (!config($id)) {
                if (!self::$startMessageShown) {
                    self::$output->writeln('<fg=red>Detected missing config value.</>');
                    self::$output->writeln('<fg=cyan>Starting EvoSC Setup.</>');
                    self::$startMessageShown = true;
                }

                while (true) {
                    $required = 'string';
                    $value    = self::askEnter(sprintf('[%s %d/%d] %s', $file, $key + 1, count($questions), $question), $default, $optional);

                    if (is_int($default)) {
                        $value    = intval($value);
                        $required = 'integer';
                    }

                    if (is_float($default)) {
                        $value    = floatval($value);
                        $required = 'float';
                    }

                    if (is_bool($default)) {
                        $value    = boolval($value);
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