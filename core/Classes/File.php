<?php

namespace esc\Classes;


use Illuminate\Support\Collection;

class File
{
    /**
     * @param string|null $fileName
     * @param bool        $json_decode
     *
     * @return null|string|Object
     */
    public static function get(string $fileName = null, bool $json_decode = false)
    {
        if (!$fileName) {
            Log::error("Could not load file $fileName");

            return null;
        }

        if (file_exists($fileName)) {
            if ($json_decode) {
                return json_decode(file_get_contents($fileName));
            }

            return file_get_contents($fileName);
        }

        return null;
    }

    public static function put(string $fileName, string $content): bool
    {
        file_put_contents($fileName, $content);

        return self::exists($fileName);
    }

    public static function appendLine($fileName, $line)
    {
        if (!file_exists($fileName)) {
            file_put_contents($fileName, $line);
        }

        file_put_contents($fileName, "\n" . $line, FILE_APPEND);
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