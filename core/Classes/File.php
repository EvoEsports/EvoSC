<?php

namespace esc\Classes;


use Illuminate\Support\Collection;

class File
{
    public static function get(string $fileName = null): ?string
    {
        if (!$fileName) {
            Log::error("Could not load file $fileName");
            return null;
        }

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
            mkdir($name, true);
        }
    }

    public static function getDirectoryContents(string $path): Collection
    {

        $files = collect(scandir($path));
        return $files;
    }

    public static function delete(string $path)
    {
        unlink($path);
    }

    public static function exists(string $filename)
    {
        return file_exists($filename);
    }
}