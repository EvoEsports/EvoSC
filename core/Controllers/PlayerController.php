<?php

namespace esc\controllers;


use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\Template;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;

class PlayerController
{
    private static $lastManialinkHash;

    public static function init()
    {
        self::createTables();

        Hook::add('PlayerDisconnect', '\esc\Controllers\PlayerController::playerDisconnect');
        Hook::add('PlayerFinish', '\esc\Controllers\PlayerController::playerFinish');
//        Hook::add('PlayerCheckpoint', '\esc\Controllers\PlayerController::playerCheckpoint');
//        Hook::add('PlayerChat', '\esc\Controllers\PlayerController::playerChat');

        Template::add('players', File::get('core/Templates/players.latte.xml'));

        ChatController::addCommand('afk', '\esc\Controllers\PlayerController::toggleAfk', 'Toggle AFK status');
    }

    private static function createTables()
    {
        Database::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Login')->unique();
            $table->string('NickName')->default("unset");
            $table->integer('Visits')->default(0);
            $table->integer('Group')->default(4);
            $table->integer('Score')->default(0);
            $table->boolean('Online')->default(false);
            $table->integer('Afk')->default(0);
            $table->boolean('Spectator')->default(false);
        });
    }

    public static function toggleAfk(Player $player)
    {
        $player->update(['Afk' => !$player->Afk]);
        self::displayPlayerlist();
    }

    public static function getPlayers(): Collection
    {
        return Player::whereOnline(true)->get();
    }

    public static function playerConnect(Player $player, bool $surpressJoinMessage = false): Player
    {
        $player->setOnline();
        $player->increment('Visits');

        Log::info($player->NickName . " joined the server.");

        if(!$surpressJoinMessage){
            ChatController::messageAllNew($player->group, ' ', $player, ' joined the server');
        }

        self::displayPlayerlist();

        return $player;
    }

    public static function playerFinish(Player $player, $score)
    {
        if ($score > 0) {
            $player->setScore($score);
            Log::info($player->NickName . " finished with time ($score) " . $player->getTime());
            self::displayPlayerlist();
        }

        if ($player->isSpectator()) {
            Server::getRpc()->forceSpectator($player->Login, 2);
            Server::getRpc()->forceSpectator($player->Login, 0);
        }

        $player->update(['Afk' => false]);
    }

    public static function playerDisconnect(Player $player = null, $disconnectReason)
    {
        if ($player == null) {
            Log::info('SERVER SHUTTING DOWN');
            exit(0);
        }

        Log::info($player->NickName . " left the server [$disconnectReason].");
        $player->setOffline();
        $player->setScore(0);
        self::displayPlayerlist();
        ChatController::messageAllNew($player, ' left the server');
    }

    public static function playerInfoChanged($infoplayerInfo)
    {
        foreach ($infoplayerInfo as $info) {
            $player = Player::firstOrCreate(['Login' => $info['Login']]);
            $player->update($info);

            if (!$player->Online) {
                self::playerConnect($player);
            }

            $player->setIsSpectator($info['SpectatorStatus'] > 0);
        }

        self::displayPlayerlist();
    }

    public static function displayPlayerlist()
    {
        $players = onlinePlayers()->where('Score', '>', 0)->sort(function (Player $a, Player $b) {
            if ($a->Score < $b->Score) {
                return -1;
            } else if ($a->Score > $b->Score) {
                return 1;
            }

            return 0;
        });

        $playersNotFinished = onlinePlayers()->where('Score', '=', 0)->sort(function (Player $a, Player $b) {
            if ($a->Score < $b->Score) {
                return -1;
            } else if ($a->Score > $b->Score) {
                return 1;
            }

            return 0;
        });


        foreach ($playersNotFinished as $player) {
            $players->add($player);
        }

        Template::showAll('players', ['players' => $players]);
    }
}