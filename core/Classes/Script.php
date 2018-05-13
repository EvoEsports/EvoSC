<?php

namespace esc\Classes;


class Script
{
    public static function getScriptParts(string $template)
    {
        $template = Template::getScript($template);
        var_dump($template);
    }
}