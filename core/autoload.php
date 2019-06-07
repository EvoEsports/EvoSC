<?php

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
        }

        //If php file, add to classes collection
        if (preg_match('/\.php$/', $classFile, $matches)) {
            $class        = collect();
            $class->file  = $file;
            $type         = explode(DIRECTORY_SEPARATOR, $path);
            $class->dir   = array_pop($type);
            $class->class = str_replace('.php', '', $classFile);
            $classes->push($class);
        }
    });
}

function getNameSpaces(\Illuminate\Support\Collection $classFiles)
{
    $classFiles = $classFiles->map(function (\Illuminate\Support\Collection $classFile) {
        $contents = file_get_contents($classFile->file);

        if (preg_match('/namespace (.+)?;/i', $contents, $matches)) {
            $classFile->namespace = $matches[1] . '\\' . $classFile->class;
        } else {
            //Abort execution when class wasn't loaded correctly
            // \esc\Classes\Log::logAddLine('autoload', "Class without namespace found: $classFile->file");
            var_dump("Class without namespace found: $classFile->file");
        }

        return $classFile;
    });

    return $classFiles;
}

function buildClassMap()
{
    global $classes;

    $dirs = ['Interfaces', 'Classes', 'Controllers', 'Models', 'Modules', '..' . DIRECTORY_SEPARATOR . 'Migrations'];

    foreach ($dirs as $dir) {
        getClassesInDirectory($classes, __DIR__ . DIRECTORY_SEPARATOR . $dir);
    }

    $classes = getNameSpaces($classes);
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
            die("Trying to load non-existant file: " . $class->file);
        }
    } else {
        // \esc\Classes\Log::logAddLine('class_loader', 'Class not found: ' . $className, isVeryVerbose());
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

buildClassMap();

spl_autoload_register('esc_class_loader');