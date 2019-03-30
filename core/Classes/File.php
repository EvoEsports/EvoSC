<?php

namespace esc\Classes;


use Illuminate\Support\Collection;

/**
 * Class File
 *
 * Create/delete/update/append files, read/create directories.
 *
 * @package esc\Classes
 */
class File
{
    /**
     * Get the contents of a file and optionally json decode them.
     *
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

    /**
     * Overwrite or create a file with the given content. Returns true if file exists.
     *
     * @param string $fileName
     * @param string $content
     *
     * @return bool
     */
    public static function put(string $fileName, string $content): bool
    {
        file_put_contents($fileName, $content);

        return self::exists($fileName);
    }

    /**
     * Append a single line to a file.
     *
     * @param $fileName
     * @param $line
     */
    public static function appendLine($fileName, $line)
    {
        if (!file_exists($fileName)) {
            file_put_contents($fileName, $line);
        }

        file_put_contents($fileName, "\n" . $line, FILE_APPEND);
    }

    /**
     * Creates a directory
     *
     * @param string $name
     */
    public static function createDirectory(string $name)
    {
        if (!is_dir($name)) {
            Log::info("Creating directory: $name");
            mkdir($name, true);
        }
    }

    /**
     * Gets all files in the directory, you can optionally filter them with a RegEx-pattern.
     *
     * @param string      $path
     * @param string|null $filterPattern
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getDirectoryContents(string $path, string $filterPattern = null): Collection
    {
        if ($filterPattern) {
            return collect(scandir($path))->filter(function ($file) use ($filterPattern) {
                return preg_match($filterPattern, $file);
            });
        }

        return collect(scandir($path));
    }

    /**
     * Get all files in a directory recursively.
     *
     * @param string $baseDirectory
     * @param string $filterPattern
     *
     * @return \Illuminate\Support\Collection
     */
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

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function delete(string $path): bool
    {
        if (file_exists($path) && is_file($path)) {
            unlink($path);
            Log::logAddLine('File', 'Deleted file: ' . $path);

            return true;
        }

        return false;
    }

    /**
     * Check if a file exists.
     *
     * @param string $filename
     *
     * @return bool
     */
    public static function exists(string $filename)
    {
        return file_exists($filename);
    }
}