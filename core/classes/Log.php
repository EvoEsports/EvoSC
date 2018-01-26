<?php

namespace esc\classes;


class Log
{
    public static function logAddLine($string)
    {
        $date = date("Y-m-d", time());
        $time = date("[H:i:s]", time());
        $logFile = sprintf("logs/%s.txt", $date);

        $line = sprintf("%s [ESC] %s", $time, $string);
        echo "$line\n";

        FileHandler::fileAppendLine($logFile, $line);
    }

    public static function info($message)
    {
        self::logAddLine(sprintf("Info: %s", $message));
    }

    public static function error($message)
    {
        self::logAddLine(sprintf("ERROR: %s", $message));
    }
}