<?php

namespace esc\Controllers;


use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Player;
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

    public static function createTables()
    {
        Database::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Login')->unique();
            $table->string('NickName')->default("unset");
            $table->integer('Group')->nullable();
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

        Log::info($player->NickName . " joined the server.");

        if (!$surpressJoinMessage) {
            ChatController::messageAll($player->group, ' ', $player, ' joined the server');
        }

        self::displayPlayerlist();

        return $player;
    }

    public static function playerFinish(Player $player, $score)
    {
        if ($score > 0 && ($player->Score == 0 || $score < $player->Score)) {
            $player->setScore($score);
            Log::info($player->NickName . " finished with time ($score) " . $player->getTime());
            self::displayPlayerlist();
        }

        if ($player->isSpectator()) {
            Server::forceSpectator($player->Login, 2);
            Server::forceSpectator($player->Login, 0);
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
        ChatController::messageAll($player, ' left the server');
    }

    public static function playerInfoChanged($infoplayerInfo)
    {
        foreach ($infoplayerInfo as $info) {
            if (!isset($info['Login'])) {
                Log::error("Login not set");
                var_dump($info);
                die();
            }

            if (Player::where('Login', $info['Login'])->get()->isEmpty()) {
                $player = Player::create(['Login' => $info['Login']]);
            } else {
                $player = Player::find($info['Login']);
            }

            if (!$player->stats) {
                $player->stats()->create(['Player' => $player->id]);
            }

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

        Template::showAll('esc.box', [
            'id' => 'PlayerList',
            'title' => '  LIVE RANKINGS',
            'x' => config('ui.playerlist.x'),
            'y' => config('ui.playerlist.y'),
            'rows' => 13,
            'scale' => config('ui.playerlist.scale'),
            'content' => Template::toString('players', ['players' => $players->take(15)])
        ]);
    }

    public static function hidePlayerlist()
    {
        Template::hideAll('players');
    }
}