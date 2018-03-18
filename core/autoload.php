<?php
function esc_autoloader($className)
{
    $classNameExplode = explode("\\", $className);

    $file = implode('/', $classNameExplode) . '.php';

    $file = str_replace('esc/', '', $file);

    $file = coreDir($file);

    if (file_exists($file)) {
        require_once $file;
    }
}

spl_autoload_register('esc_autoloader');