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
        $fileName = str_replace('/', DIRECTORY_SEPARATOR, $fileName);

        if (file_exists($fileName)) {
            if ($json_decode) {
                return json_decode(file_get_contents($fileName));
            }

            return file_get_contents($fileName);
        } else {
            Log::error("Could not load file $fileName");
        }

        return null;
    }

    /**
     * Overwrite or create a file with the given content. Returns true if file exists.
     *
     * @param string       $fileName
     * @param string|mixed $content
     *
     * @return bool
     */
    public static function put(string $fileName, $content, bool $jsonEncode = false): bool
    {
        $fileName = str_replace('/', DIRECTORY_SEPARATOR, $fileName);
        $dir      = str_replace(basename($fileName), '', $fileName);

        if (!is_dir(realpath($dir))) {
            mkdir(realpath($dir));
        }

        if ($jsonEncode) {
            file_put_contents($fileName, json_encode($content));
        } else {
            file_put_contents($fileName, $content);
        }

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
        $fileName = str_replace('/', DIRECTORY_SEPARATOR, $fileName);

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
        if (!is_dir($path)) {
            return collect();
        }

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

        if (!is_dir($baseDirectory)) {
            return $files;
        }

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

    public static function getFiles(string $baseDirectory, string $filterPattern = null)
    {
        $files = collect();

        File::getDirectoryContents($baseDirectory)
            ->each(function ($file) use ($baseDirectory, $filterPattern, &$files) {
                $path = $baseDirectory . DIRECTORY_SEPARATOR . $file;

                if (!is_dir($path)) {
                    //File is not directory
                    if (!$filterPattern || $filterPattern && preg_match($filterPattern, $file)) {
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
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        if (file_exists($path) && is_file($path)) {
            unlink($path);
            Log::write('Deleted file: ' . $path);

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
        $filename = str_replace('/', DIRECTORY_SEPARATOR, $filename);

        return is_file($filename) && file_exists($filename);
    }

    /**
     * Check if a directory exists.
     *
     * @param string $filename
     *
     * @return bool
     */
    public static function dirExists(string $filename)
    {
        $filename = str_replace('/', DIRECTORY_SEPARATOR, $filename);

        return is_dir($filename);
    }

    public static function makeDir(string $dir)
    {
        $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);

        mkdir($dir);
    }

    public static function rename(string $sourceFile, string $targetFile)
    {
        $sourceFile = str_replace('/', DIRECTORY_SEPARATOR, $sourceFile);
        $targetFile = str_replace('/', DIRECTORY_SEPARATOR, $targetFile);

        rename($sourceFile, $targetFile);
    }

    public static function copy(string $source, string $target)
    {
        $source = str_replace('/', DIRECTORY_SEPARATOR, $source);
        $target = str_replace('/', DIRECTORY_SEPARATOR, $target);

        copy($source, $target);
    }
}