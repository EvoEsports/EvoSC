<?php

namespace esc\classes;


class FileHandler
{
    public static function fileAppendLine($fileName, $line)
    {
        if(file_exists($fileName)){
           $data = file_get_contents($fileName);
        }else{
            $data = "";
        }

        $data .= "\n" . $line;

        file_put_contents($fileName, $data);
    }
}