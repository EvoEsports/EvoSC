<?php

namespace EvoSC\Classes;


use EvoSC\Exceptions\FilePathNotAbsoluteException;
use Illuminate\Support\Collection;

/**
 * Class File
 *
 * Create/delete/update/append files, read/create directories.
 *
 * @package EvoSC\Classes
 */
class File
{
    /**
     * Get the contents of a file and optionally json decode them.
     *
     * @param string|null $fileName
     * @param bool $json_decode
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
            Log::warning("Could not load file '$fileName'");
        }

        return null;
    }

    /**
     * Overwrite or create a file with the given content. Returns true if file exists.
     *
     * @param string $fileName
     * @param string|mixed $content
     *
     * @param bool $jsonEncode
     * @return bool
     */
    public static function put(string $fileName, $content, bool $jsonEncode = false): bool
    {
        $fileName = str_replace('/', DIRECTORY_SEPARATOR, $fileName);
        $dir = str_replace(basename($fileName), '', $fileName);


        if (!self::dirExists($dir)) {
            self::makeDir($dir);
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
            self::put($fileName, $line);
        }

        file_put_contents($fileName, "\n" . $line, FILE_APPEND);
    }

    /**
     * Gets all files in the directory, you can optionally filter them with a RegEx-pattern.
     *
     * @param string $path
     * @param string|null $filterPattern
     *
     * @return Collection
     */
    public static function getDirectoryContents(string $path, string $filterPattern = null): Collection
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

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
     * @return Collection
     */
    public static function getFilesRecursively(string $baseDirectory, string $filterPattern): Collection
    {
        $baseDirectory = str_replace('/', DIRECTORY_SEPARATOR, $baseDirectory);
        $files = collect();

        if (!is_dir($baseDirectory)) {
            return $files;
        }

        File::getDirectoryContents($baseDirectory)
            ->each(function ($file) use ($baseDirectory, $filterPattern, &$files) {
                $path = $baseDirectory . DIRECTORY_SEPARATOR . $file;

                if (is_dir($path) && !in_array($file, ['.', '..'])) {
                    //Check directory contents
                    $files = collect([...$files, ...self::getFilesRecursively($path, $filterPattern)]);
                } else {
                    //File is not directory
                    if (preg_match($filterPattern, $file)) {
                        //Add template
                        $files->push(realpath($path));
                    }
                }
            });

        return $files;
    }

    public static function getFiles(string $baseDirectory, string $filterPattern = null)
    {
        $baseDirectory = str_replace('/', DIRECTORY_SEPARATOR, $baseDirectory);
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
            Log::warning('Deleted file: ' . $path);

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
     * @param string $filename-
     *
     * @return bool
     */
    public static function dirExists(string $filename)
    {
        $filename = str_replace('/', DIRECTORY_SEPARATOR, $filename);

        return is_dir($filename);
    }

    /**
     * @param string $dir
     * @throws FilePathNotAbsoluteException
     */
    public static function makeDir(string $dir)
    {
        $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);

        if ((isWindows() && !preg_match('/^\\w:(\\\|\\/)/i', $dir)) || (!isWindows() && substr($dir, 0, 1) != DIRECTORY_SEPARATOR)) {
            throw new FilePathNotAbsoluteException("Directory path '$dir' is not absolute.");
        }

        if (!is_dir($dir)) {
            self::createDirUntilExists($dir);
            Log::info("Directory '$dir' created.");
        }
    }

    /**
     * @param string $sourceFile
     * @param string $targetFile
     * @throws FilePathNotAbsoluteException
     */
    public static function rename(string $sourceFile, string $targetFile)
    {
        $sourceFile = str_replace('/', DIRECTORY_SEPARATOR, $sourceFile);
        $targetFile = str_replace('/', DIRECTORY_SEPARATOR, $targetFile);

        if (!self::dirExists(dirname($targetFile))) {
            self::makeDir(dirname($targetFile));
        }

        rename($sourceFile, $targetFile);
    }

    /**
     * @param string $source
     * @param string $target
     * @throws FilePathNotAbsoluteException
     */
    public static function copy(string $source, string $target)
    {
        $source = str_replace('/', DIRECTORY_SEPARATOR, $source);
        $target = str_replace('/', DIRECTORY_SEPARATOR, $target);

        if (!self::dirExists(dirname($target))) {
            self::makeDir(dirname($target));
        }

        copy($source, $target);
    }

    /**
     * PRIVATE METHODS
     */

    /**
     * @param string $startDir
     */
    private static function createDirUntilExists(string $startDir)
    {
        $levels = collect(explode(DIRECTORY_SEPARATOR, $startDir));

        if ($levels->last() == "") {
            $levels->pop();
        }

        $toCreate = $levels->implode(DIRECTORY_SEPARATOR);
        $levels->pop();
        $parentDir = $levels->implode(DIRECTORY_SEPARATOR);

        if (!is_dir($parentDir)) {
            self::createDirUntilExists($parentDir);
        }

        mkdir($toCreate);
    }
}