<?php

namespace esc\controllers;


use esc\classes\Config;
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

    public static function playerDisconnect(Player $player = null, $disconnectReason)
    {
        if ($player == null) {
            Log::info('SERVER SHUTTING DOWN');
            Log::info('SERVER SHUTTING DOWN');
            Log::info('SERVER SHUTTING DOWN');
            exit(0);
        }

        Log::info($player->nick(true) . " left the server [$disconnectReason].");
        $player->setOffline();
        $player->setScore(0);
        self::displayPlayerlist();
        ChatController::messageAll('$%s%s $z$s$%sleft the server', config('color.secondary'), $player->NickName, config('color.primary'));
    }

    public static function playerInfoChanged($infoplayerInfo)
    {
        foreach ($infoplayerInfo as $info) {
            $player = self::getPlayerByLogin($info['Login']);

            if (!$player) {
                $player = Player::firstOrCreate(['Login' => $info['Login']]);
                $player->update($info);
            }

            if (!$player->Online) {
                $player->setOnline();
                $player->increment('Visits');
                self::$players = self::getPlayers()->add($player)->unique();

                Log::info($player->nick(true) . " joined the server.");
                ChatController::messageAll('$%s%s $%s%s $z$s$%sjoined the server',
                    config('color.primary'), $player->group->Name, config('color.secondary'), $player->nick(),
                    config('color.primary'));
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
        $players = self::getPlayers()->where('LastScore', '>', 0)->sort(function (Player $a, Player $b) {
            if ($a->LastScore < $b->LastScore) {
                return -1;
            } else if ($a->LastScore > $b->LastScore) {
                return 1;
            }

            return 0;
        });

        $playersNotFinished = self::getPlayers()->diff($players);

        foreach ($playersNotFinished as $player) {
            $players->add($player);
        }

        Template::showAll('players', ['players' => $players]);
    }
}