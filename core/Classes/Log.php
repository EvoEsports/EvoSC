<?php

namespace esc\Classes;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Log
 *
 * Logging and colored cli-output.
 *
 * @package esc\Classes
 */
class Log
{
    /**
     * @var OutputInterface
     */
    private static $output;

    /**
     * Get the output-interface
     *
     * @return OutputInterface|null
     */
    public static function getOutput(): ?OutputInterface
    {
        return self::$output;
    }

    /**
     * Write a line to the cli.
     *
     * @param string $line
     */
    public static function writeLn(string $line)
    {
        $output = self::getOutput();
        $output->writeln(stripAll($line));
    }

    /**
     * Add a log entry and output it to cli as default. You can use:
     * - isVerbose() (-v)
     * - isVeryVerbose() (-vv)
     * - isDebug() (-vvv)
     * to manage output to cli (all lines will be added to the log-file, independent from verbosity-level).
     *
     * @param string $prefix
     * @param string $string
     * @param bool   $echo
     */
    public static function logAddLine(string $prefix, string $string, $echo = true)
    {
        $date    = date("Y-m-d", time());
        $time    = date("H:i:s", time());
        $logFile = sprintf("logs/%s.txt", $date);

        if ($prefix == 'Info') {
            $prefix = 'i';
        }

        list($childClass, $caller) = debug_backtrace(false, 2);

        if (isset($caller['class']) && isset($caller['type'])) {
            $callingClass = $caller['class'] . $caller['type'] . $caller['function'];
        }else{
            $callingClass = $caller['function'];
        }


        if (isVerbose()) {
            $callingClass .= '(';

            foreach ($caller['args'] as $key => $arg) {
                $add = '<fg=yellow>';

                if (is_array($arg)) {
                    $add .= 'Array';
                } else {
                    if (is_object($arg)) {
                        $add .= get_class($arg);
                    } else {
                        $add .= '"' . $arg . '"';
                    }
                }

                $callingClass .= $add . '</>';

                if ($key + 1 < count($caller['args'])) {
                    $callingClass .= ', ';
                }
            }

            $callingClass .= ')';
        } else {
            $callingClass .= '(...)';
        }

        if (isDebug()) {
            $callingClass .= "\nData: " . json_encode($caller['args']);
        }

        $line = sprintf("[%s] %s: %s", $time, $callingClass, $string);
        $line = stripAll($line);

        if ($echo == true || isVeryVerbose()) {
            switch ($prefix) {
                case 'Module':
                case 'Modules':
                case 'Hook':
                case 'Keybinds':
                    self::writeLn("<fg=blue>$line</>");
                    break;

                case 'i':
                case 'Info':
                case 'BOOT':
                    self::writeLn("<info>$line</info>");
                    break;

                case 'Warning':
                    self::writeLn("<fg=red>$line</>");
                    break;

                case 'Dedimania':
                case 'DedimaniaApi':
                    self::writeLn("<fg=green>$line</>");
                    break;

                case 'ERROR':
                    self::writeLn("<error>$line</error>");
                    break;

                case 'Chat':
                    self::writeLn("<fg=yellow;>$line</>");
                    break;

                case 'Debug':
                    self::writeLn($line);
                    break;

                default:
                    self::writeLn($line);
            }
        }

        File::appendLine($logFile, $line);
    }

    /**
     * Log a info-message.
     *
     * @param      $message
     * @param bool $echo
     */
    public static function info($message, bool $echo = true)
    {
        self::logAddLine('Info', $message, $echo);
    }

    /**
     * Log a error-message.
     *
     * @param      $message
     * @param bool $echo
     */
    public static function error($message, bool $echo = true)
    {
        self::logAddLine("ERROR", $message, $echo);
    }

    /**
     * Log a warning-message
     *
     * @param      $message
     * @param bool $echo
     */
    public static function warning($message, bool $echo = true)
    {
        self::logAddLine('Warning', $message, $echo);
    }

    /**
     * Log a chat-message.
     *
     * @param $nick
     * @param $message
     */
    public static function chat($nick, $message)
    {
        $line = "$nick: ";
        $line .= $message;
        self::logAddLine(stripAll($line), true);
    }

    /**
     * Do not use (internal function). Set the output interface.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public static function setOutput(OutputInterface $output)
    {
        self::$output = $output;
    }
}