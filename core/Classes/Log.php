<?php

namespace esc\Classes;

use Symfony\Component\Console\Output\OutputInterface;

class Log
{
    private static $output;

    public static function setOutput(OutputInterface $output)
    {
        self::$output = $output;
    }

    public static function getOutput(): ?OutputInterface
    {
        return self::$output;
    }

    public static function writeLn(string $line)
    {
        $output = self::getOutput();
        $output->writeln(stripAll($line));
    }

    public static function logAddLine(string $prefix, string $string, $echo = true)
    {
        $date    = date("Y-m-d", time());
        $time    = date("H:i:s", time());
        $logFile = sprintf("logs/%s.txt", $date);

        if ($prefix == 'Info') {
            $prefix = 'i';
        }

        $line = sprintf("[%s] [%s] %s", $time, $prefix, $string);
        $line = stripAll($line);

        if ($echo == true || isVeryVerbose()) {
            switch ($prefix) {
                case 'Module':
                case 'Modules':
                case 'Hook':
                    self::writeLn("<fg=blue>$line</>");
                    break;

                case 'i':
                case 'Info':
                    self::writeLn("<info>$line</info>");
                    break;

                case 'Warning':
                    self::writeLn("<fg=red>$line</>");
                    break;

                case 'Dedimania':
                    self::writeLn($line);
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

    public static function info($message, bool $echo = true)
    {
        self::logAddLine('Info', $message, $echo);
    }

    public static function error($message, bool $echo = true)
    {
        self::logAddLine("ERROR", $message, $echo);
        self::debug($message);
    }

    public static function warning($message, bool $echo = true)
    {
        self::logAddLine('Warning', $message, $echo);
    }

    public static function hook($message, $echo = false)
    {
        self::logAddLine('Hook', $message, $echo);
    }

    private static function debug($message, bool $echo = true)
    {
        self::logAddLine('Debug', $message, false);
    }

    public static function music($message, bool $echo = true)
    {
        self::logAddLine('Music-Server', $message, $echo);
    }

    public static function chat($nick, $message)
    {
        $line = "$nick: ";
        $line .= $message;
        self::logAddLine(stripAll($line), true);
    }
}