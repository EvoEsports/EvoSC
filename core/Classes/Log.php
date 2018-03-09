<?php

namespace esc\Classes;


class Log
{
    private static $prefix = '';

    public static function logAddLine(string $prefix, string $string, $echo = false)
    {
        $date = date("Y-m-d", time());
        $time = date("H:i:s", time());
        $logFile = sprintf("logs/%s.txt", $date);

        $line = sprintf("[%s] [%s] %s", $time, $prefix, $string);
        $line = stripAll($line);

        if($echo){
            switch($prefix){
                case 'Module':
                    Console::log($line, 'blue');
                    break;

                case 'Info':
                    Console::log($line, 'blue');
                    break;

                case 'Hook':
                    Console::log($line, 'light_green');
                    break;

                case 'Warning':
                    Console::log($line, 'red');
                    break;

                case '[!] ERROR':
                    Console::log($line, 'black', true, 'red');
                    break;

                case 'Debug':
                    Console::log($line, 'dark_gray', true, 'magenta');
                    break;

                default:
                    Console::log($line, 'normal');
            }
        }

        File::fileAppendLine($logFile, $line);
    }

    public static function info($message, bool $echo = true)
    {
        self::logAddLine('Info', $message, $echo);
    }

    public static function error($message, bool $echo = true)
    {
        self::logAddLine("[!] ERROR", $message, $echo);
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
        self::logAddLine('Debug', $message, $echo);
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