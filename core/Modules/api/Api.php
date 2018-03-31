<?php

class Api
{
    public function __construct()
    {
        if (config('server.cp', false) == true) {
            Api::start();
        }

        self::createTables();
    }

    public static function start()
    {
        $phpBinaryFinder = new Symfony\Component\Process\PhpExecutableFinder();
        $phpBinaryPath = $phpBinaryFinder->find();

        $musicServer = new Symfony\Component\Process\Process($phpBinaryPath . ' -S 0.0.0.0:5200 ' . __DIR__ . '/api/public/index.php');
        $musicServer->start();
    }

    private static function createTables()
    {
        \esc\Classes\Database::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('login')->unique();
            $table->string('password');
            $table->string('token');
            $table->rememberToken();
            $table->timestamps();
        });
    }
}