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

    public static function put(string $fileName, string $content): bool
    {
        file_put_contents($fileName, $content);

        return self::exists($fileName);
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

    public static function getDirectoryContents(string $path, string $filterPattern = null): Collection
    {
        if ($filterPattern) {
            return collect(scandir($path))->filter(function ($file) use ($filterPattern) {
                return preg_match($filterPattern, $file);
            });
        }

        return collect(scandir($path));
    }

    public static function getFilesRecursively(string $baseDirectory, string $filterPattern): Collection
    {
        $files = collect();

        File::getDirectoryContents($baseDirectory)
            ->each(function ($file) use ($baseDirectory, $filterPattern, &$files) {
                $path = $baseDirectory . DIRECTORY_SEPARATOR . $file;

                if (is_dir($path) && !in_array($file, ['.', '..'])) {
                    //Check directory contents
                    $files = $files->merge(self::getFilesRecursively($path, $filterPattern));
                } else {
                    //File is not directory
                    if (preg_match($filterPattern, $file)) {
                        //Add template
                        $files->push($path);
                    }
                }
            });

        return $files;
    }

    public static function delete(string $path): bool
    {
        if (file_exists($path) && is_file($path)) {
            unlink($path);
            Log::logAddLine('File', 'Deleted file: ' . $path);

            return true;
        }

        return false;
    }

    public static function exists(string $filename)
    {
        return file_exists($filename);
    }
}