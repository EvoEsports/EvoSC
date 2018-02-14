<?php

use esc\classes\Config;
use esc\classes\Log;
use esc\classes\Timer;
use Maniaplanet\DedicatedServer\Connection;

include 'core/autoload.php';
include 'vendor/autoload.php';

Log::info("Starting...");

Log::info("Loading config files.");
Config::loadConfigFiles();

\esc\controllers\HookController::initialize();

\esc\classes\Database::initialize();

try{
    Log::info("Connecting to server...");
    $rpc = Connection::factory(Config::get('server.ip'), Config::get('server.port'), 5, Config::get('server.rpc.login'), Config::get('server.rpc.password'));
    \esc\controllers\RpcController::initialize(Config::get('server.ip'), Config::get('server.port'), 5, Config::get('server.rpc.login'), Config::get('server.rpc.password'));
    Log::info("Connection established.");
}catch(\Exception $e){
    Log::error("Connection to server failed.");
    throw new Exception("Connection to server failed.");
}

\esc\controllers\ChatController::initialize();

\esc\controllers\MapController::initialize();

\esc\controllers\ModuleController::loadModules('core/Modules');
\esc\controllers\ModuleController::loadModules('modules');

\esc\classes\RestClient::initialize();
\esc\classes\RestClient::$serverName = 'Brakers dev server LOGIN brakertest2';

\esc\controllers\PlayerController::initialize();

foreach(\esc\controllers\RpcController::getRpc()->getPlayerList() as $player){
    if(!\esc\models\Player::exists($player->login)){
        $ply = new \esc\models\Player();
        $ply->Login = $player->login;
        $ply->NickName = $player->nickName;
        $ply->LadderScore = $player->ladderRanking;
        $ply->save();
    }

    $ply = \esc\models\Player::find($player->login);

    if($ply){
        \esc\controllers\PlayerController::playerConnect($ply);
        $ply->setScore(0);
    }
}

//\esc\controllers\PlayerController::playerConnect(\esc\models\Player::find('reaby')->setOffline());

while (true) {
    Timer::startCycle();

    \esc\controllers\HookController::handleCallbacks(\esc\controllers\RpcController::executeCallbacks());

    usleep(Timer::getNextCyclePause());
}

Log::info("Shutting down.");