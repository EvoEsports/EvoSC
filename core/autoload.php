<?php
function esc_autoloader($className)
{
    $classNameExplode = explode("\\", $className);

    $realClassName = "core";

    for ($i = 1; $i < count($classNameExplode); $i++) {
        $realClassName .= "/" . $classNameExplode[$i];
    }

    $file = $realClassName . '.php';

    if (file_exists($file)) {
//        echo "[[AUTOLOAD]] --------------------------------------> ".str_pad($className, 40, ' ',STR_PAD_RIGHT)." from $file\n";
        require_once $file;
    }
}

spl_autoload_register('esc_autoloader');