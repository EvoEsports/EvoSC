<?php

namespace esc\Classes;


class Log
{
    private static $prefix = '';

    public static function logAddLine($string, $echo = false)
    {
        $date = date("Y-m-d", time());
        $time = date("[H:i:s]", time());
        $logFile = sprintf("logs/%s.txt", $date);

        $line = preg_replace('/\$[a-f\d]{3}|\$i|\$s|\$w|\$n|\$m|\$g|\$o|\$z|\$t|\$l\[.+\]/i', '', "$time $string");

        if ($echo) {
            echo "$line\n";
        }

        File::fileAppendLine($logFile, $line);
    }

    public static function info($message, bool $echo = true)
    {
        self::logAddLine(sprintf("Info: %s", $message), $echo);
    }

    public static function error($message, bool $echo = true)
    {
        self::logAddLine(sprintf("[!] ERROR: %s", $message), $echo);
        self::debug($message);
    }

    public static function warning($message, bool $echo = true)
    {
        self::logAddLine(sprintf("Warning: %s", $message), $echo);
    }

    public static function hook($message, $echo = false)
    {
        self::logAddLine(sprintf("Hook: %s", $message), $echo);
    }

    private static function debug($message, bool $echo = true)
    {
        self::logAddLine(sprintf("Debug: %s", $message), $echo);
    }

    public static function music($message, bool $echo = true)
    {
        self::logAddLine(sprintf("Music-Server: %s", $message), $echo);
    }

    public static function chat($nick, $message)
    {
        $line = "$nick: ";
        $line .= $message;
        self::logAddLine($line, true);
    }
}