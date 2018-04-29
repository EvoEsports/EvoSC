<?php

$classes = collect([]);

function getClassesInDirectory(&$classes, $path)
{
    return collect(scandir($path))->filter(function ($string) {
        return substr($string, 0, 1) != '.';
    })->each(function ($classFile) use ($classes, $path) {
        $file = $path . '/' . $classFile;

        if (is_dir($file)) {
            //Get classes from subdirs
            getClassesInDirectory($classes, $file);
        }

        //If php file, add to classes collection
        if (preg_match('/\.php$/', $classFile, $matches)) {
            $class = collect();
            $class->file = $file;
            $type = explode(DIRECTORY_SEPARATOR, $path);
            $class->type = array_pop($type);
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
            echo "Class without namespace found: $classFile->file \n";
            exit(0);
        }

        return $classFile;
    });

    return $classFiles;
}

function buildClassMap()
{
    global $classes;

    $dirs = ['Classes', 'Controllers', 'Models', 'Modules'];

    foreach ($dirs as $dir) {
        getClassesInDirectory($classes, __DIR__ . '/' . $dir);
    }

    $classes = getNameSpaces($classes);
}

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
//        die("Trying to load unknown class: $className \n");
    }
}

buildClassMap();

spl_autoload_register('esc_class_loader');

//function esc_autoloader($className)
//{
//    $classNameExplode = explode("\\", $className);
//
//    $file = implode('/', $classNameExplode) . '.php';
//
//    $file = str_replace('esc/', '', $file);
//
//    $file = coreDir($file);
//
//    if (file_exists($file)) {
//        require_once $file;
//    }
//}
//
//spl_autoload_register('esc_autoloader');