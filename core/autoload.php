<?php
function esc_autoloader($className)
{
    $classNameExplode = explode("\\", $className);

    $realClassName = '';

    if($classNameExplode[0] == 'esc'){
        $realClassName = "core";
    }elseif ($classNameExplode[0] == 'module'){
        $realClassName = "modules";
    }

    for ($i = 1; $i < count($classNameExplode); $i++) {
        $realClassName .= "/" . $classNameExplode[$i];
    }

    $file = $realClassName . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
}

spl_autoload_register('esc_autoloader');