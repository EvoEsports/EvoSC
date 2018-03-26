<?php

class Api
{
    public function __construct()
    {
        if(config('server.cp', false) == true){
            Api::start();
        }
    }

    public static function start()
    {
        $phpBinaryFinder = new Symfony\Component\Process\PhpExecutableFinder();
        $phpBinaryPath = $phpBinaryFinder->find();

        $musicServer = new Symfony\Component\Process\Process($phpBinaryPath . ' -S 0.0.0.0:5200 ' . __DIR__ . '/server-api/public/index.php');
        $musicServer->start();
    }
}