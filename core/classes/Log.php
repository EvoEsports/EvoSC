<?php

namespace esc\classes;


class Log
{
    private static $prefix = '[ESC]';

    public static function logAddLine($string, $echo = false)
    {
        $date = date("Y-m-d", time());
        $time = date("[H:i:s]", time());
        $logFile = sprintf("logs/%s.txt", $date);

        $line = "$time $string";

        if ($echo) {
            echo "$line\n";
        }

        File::fileAppendLine($logFile, $line);
    }

    public static function info($message)
    {
        self::logAddLine(sprintf(self::$prefix . " Info: %s", $message), true);
    }

    public static function error($message)
    {
        self::logAddLine(sprintf(self::$prefix . " [!] ERROR: %s", $message), true);
    }

    public static function warning($message)
    {
        self::logAddLine(sprintf(self::$prefix . " Warning: %s", $message), true);
    }

    public static function hook($message)
    {
        self::logAddLine(sprintf(self::$prefix . " Hook: %s", $message));
    }

    public static function chat($nick, $message)
    {
        $line = "$nick: ";
        $line .= $message;
        self::logAddLine($line, true);
    }
}