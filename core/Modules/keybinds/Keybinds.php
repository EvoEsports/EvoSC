<?php

use esc\Classes\File;
use esc\Classes\Template;

class Keybinds
{
    public static function init()
    {
        Template::add('keybinds', File::get(__DIR__ . '/Templates/keybinds.latte.xml'));
    }
}