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
    private static $players;
    private static $lastManialinkHash;

    public static function initialize()
    {
        self::createTables();

        Hook::add('PlayerDisconnect', '\esc\controllers\PlayerController::playerDisconnect');
        Hook::add('PlayerFinish', '\esc\controllers\PlayerController::playerFinish');

        Template::add('players', File::get('core/Templates/players.latte.xml'));

        ChatController::addCommand('afk', '\esc\controllers\PlayerController::toggleAfk', 'Toggle AFK status');

        self::$players = new Collection();
    }

    private static function createTables()
    {
        Database::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Login')->unique();
            $table->string('NickName')->default("unset");
            $table->integer('Visits')->default(0);
            $table->integer('Group')->default(4);
            $table->integer('LastScore')->default(0);
            $table->boolean('Online')->default(false);
        });
    }

    public static function toggleAfk(Player $player)
    {
        if (!isset($player->afk)) {
            $player->afk = true;
        }

        $player->afk = !$player->afk;

        self::displayPlayerlist();
    }

    public static function getPlayers(): Collection
    {
        return self::$players;
    }

    public static function playerConnect(Player $player): Player
    {
        $player->increment('Visits');

        if (!$player->Online) {
            $player->setOnline();
            $player->setScore(0);
        }

        self::getPlayers()->add($player);
        Log::info($player->nick(true) . " joined the server.");

        self::displayPlayerlist();

        return $player;
    }

    public static function playerFinish(Player $player, $score)
    {
        if ($score > 0) {
            $player->setScore($score);
            Log::info($player->nick() . " finished with time ($score) " . $player->getTime());
            self::displayPlayerlist();
        }

        if ($player->isSpectator()) {
            ServerController::getRpc()->forceSpectator($player->Login, 2);
            ServerController::getRpc()->forceSpectator($player->Login, 0);
        }
    }

    public static function playerDisconnect(Player $player, $disconnectReason)
    {
        Log::info($player->nick(true) . " left the server [$disconnectReason].");
        $player->setOffline();
        $player->setScore(0);
        self::displayPlayerlist();
        ChatController::messageAll("$player->NickName left the server.");
    }

    public static function playerInfoChanged($infoplayerInfo)
    {
        foreach ($infoplayerInfo as $info) {
            $player = self::getPlayerByLogin($info['Login']);

            if (!$player) {
                if (Player::exists($info['Login'])) {
                    $player = Player::find($info['Login']);
                } else {
                    $player = new \esc\models\Player();
                    $player->Login = $info['Login'];
                    $player->NickName = $info['NickName'];
                    $player->LadderScore = $info['LadderScore'];
                    $player->save();
                }
            }

            if (!$player->Online) {
                $player->setOnline();
                $player->increment('visits');
                self::$players = self::getPlayers()->add($player)->unique();
                ChatController::messageAll("$18f" . $player->group->Name . " $player->NickName \$z\$s$18fjoined the server.");
            }

            $player->update($info);
            $player->setIsSpectator($info['SpectatorStatus'] > 0);
        }

        self::displayPlayerlist();
    }

    public static function getPlayerByLogin(string $login): ?Player
    {
        $player = self::getPlayers()->where('Login', $login)->first();
        return $player;
    }

    public static function displayPlayerlist()
    {
        $players = self::getPlayers()->sort(function (Player $a, Player $b) {
            if ($a->score == 0) {
                return 1000;
            }

            if ($a->score < $b->score) {
                return -1;
            } else if ($a->score > $b->score) {
                return 1;
            }

            return 0;
        });

        Template::showAll('players', ['players' => $players]);
    }
}