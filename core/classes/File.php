<?php

namespace esc\classes;


class File
{
    public static function get(string $fileName): ?string
    {
        if (file_exists($fileName)) {
            return file_get_contents($fileName);
        }

        return null;
    }

    public static function put(string $fileName, string $content)
    {
        file_put_contents($fileName, $content);
    }

    public static function fileAppendLine($fileName, $line)
    {
        if (file_exists($fileName)) {
            $data = file_get_contents($fileName);
        } else {
            $data = "";
        }

        $data .= "\n" . $line;

        file_put_contents($fileName, $data);
    }

    public static function createDirectory(string $name)
    {
        if (!is_dir($name)) {
            Log::info("Creating directory: $name");
            mkdir($name);
        }
    }
}