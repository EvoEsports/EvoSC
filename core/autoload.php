<?php

use esc\Classes\Server;

$classes = collect([]);

function getClassesInDirectory(&$classes, $path)
{
    return collect(scandir($path))->filter(function ($string) {
        return substr($string, 0, 1) != '.';
    })->each(function ($classFile) use ($classes, $path) {
        $file = $path . DIRECTORY_SEPARATOR . $classFile;

        if (is_dir($file)) {
            //Get classes from subdirs
            getClassesInDirectory($classes, $file);
            return;
        }

        //If php file, add to classes collection
        if (preg_match('/\.php$/', $classFile, $matches)) {
            $class = collect();
            $class->file = $file;
            $type = explode(DIRECTORY_SEPARATOR, $path);
            $class->dir = array_pop($type);
            $class->class = str_replace('.php', '', $classFile);
            $classes->push($class);
        }
    });
}

function buildClassMap()
{
    global $classes;

    $dirs = [
        'Interfaces', 'Classes', 'Commands', 'Controllers', 'Models', 'Modules', '..' . DIRECTORY_SEPARATOR . 'Migrations', '..' . DIRECTORY_SEPARATOR . 'modules'
    ];

    if (is_dir(realpath('..' . DIRECTORY_SEPARATOR . 'modules'))) {
        array_push($dirs, '..' . DIRECTORY_SEPARATOR . 'modules');
    }

    foreach ($dirs as $dir) {
        getClassesInDirectory($classes, realpath(__DIR__ . DIRECTORY_SEPARATOR . $dir));
    }

    $classes = getNameSpaces($classes);
}

function getNameSpaces(\Illuminate\Support\Collection $classFiles)
{
    $classFiles = $classFiles->transform(function (\Illuminate\Support\Collection $classFile) {
        $contents = file_get_contents($classFile->file);

        if (preg_match('/namespace (.+)?;/i', $contents, $matches)) {
            $classFile->namespace = $matches[1] . '\\' . $classFile->class;
        } else {
            //Abort execution when class wasn't loaded correctly
            // \esc\Classes\Log::write("Class without namespace found: $classFile->file");
            var_dump("Class without namespace found: $classFile->file");
        }

        return $classFile;
    });

    return $classFiles;
}

/**
 * @param $className
 *
 * @throws Exception
 */
function esc_class_loader($className)
{
    global $classes;

    $class = $classes->where('namespace', $className)->first();

    if ($class) {
        if (file_exists($class->file)) {
            require_once $class->file;
        } else {
            die("Trying to load non-existent file: " . $class->file);
        }
    } else {
        // \esc\Classes\Log::write('Class not found: ' . $className, isVeryVerbose());
        if ($className != 'Doctrine\DBAL\Driver\PDOConnection') {
            var_dump('Class not found: ' . $className);
        }
    }
}

function classes(): \Illuminate\Support\Collection
{
    global $classes;

    return $classes;
}

function shutdown()
{
    global $_restart;

    if ($_restart) {
        \esc\Classes\Log::warning('Automatic restarting disabled (unstable).');
        /*
        switch (pcntl_fork()) {
            case 0:
                echo "Child is starting.\n";
                pcntl_exec('/usr/bin/php', $_SERVER['argv']);
                exit(0);

            default:
                Server::chatEnableManualRouting(false, false);
                echo "Parent is exiting.\n";
                exit(0);
        }
        */
    }
}

buildClassMap();

spl_autoload_register('esc_class_loader');
register_shutdown_function('shutdown');