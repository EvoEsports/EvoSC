<?php

namespace esc\controllers;


use esc\classes\Database;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\ManiaBuilder;
use esc\ManiaLink\Elements\Label;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Maniaplanet\DedicatedServer\Xmlrpc\ParseException;

class PlayerController
{
    private static $players;
    private static $lastManialinkHash;

    public static function initialize()
    {
        self::createTables();

        Hook::add('PlayerDisconnect', '\esc\controllers\PlayerController::playerDisconnect');
        Hook::add('PlayerFinish', '\esc\controllers\PlayerController::playerFinish');

        ChatController::addCommand('afk', '\esc\controllers\PlayerController::toggleAfk', 'Toggle AFK status');

        self::$players = new Collection();
    }

    private static function createTables()
    {
        Database::create('players', function(Blueprint $table){
            $table->increments('id');
            $table->string('Login')->unique();
            $table->string('NickName')->default("unset");
            $table->integer('Visits')->default(0);
            $table->float('LadderScore')->default(0);
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
        $player->setOnline();

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
    }

    public static function playerDisconnect(Player $player, $disconnectReason)
    {
        Log::info($player->nick(true) . " left the server [$disconnectReason].");
        $player->setOffline();
        self::displayPlayerlist();
        ChatController::messageAll("$player->NickName left the server.");
    }

    public static function playerInfoChanged($infoplayerInfo)
    {
        /*  struct SPlayerInfo
            {
              string Login;
              string NickName;
              int PlayerId;
              int TeamId;
              int SpectatorStatus;
              int LadderRanking;
              int Flags;
            }
        */


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

            if (!$player->isOnline()) {
                $player->setOnline();
                $player->increment('visits');
                self::$players = self::getPlayers()->add($player)->unique();
                ChatController::messageAll("$player->NickName joined the server.");
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

    private static function sortPlayerList(Player $p1, Player $p2)
    {
        var_dump($p1);
    }

    public static function displayPlayerlist()
    {
        $builder = new ManiaBuilder('Playerlist', ManiaBuilder::STICK_LEFT, ManiaBuilder::STICK_TOP, 90, 60, .55, ['padding' => 3, 'bgcolor' => '0009']);

        $label = new Label("Playerlist", ['width' => '30', 'textsize' => 5, 'height' => 12]);
        $builder->addRow($label);

        $players = self::getPlayers()->sort(function (Player $a, Player $b) {
            if ($a->score == 0) {
                return 10;
            }

            if ($a->score < $b->score) {
                return -1;
            } else if ($a->score > $b->score) {
                return 1;
            }

            return 0;
        });

        $i = 1;
        foreach ($players as $index => $player) {
            $position = new Label("$i.", ['width' => 8, 'textsize' => 3, 'valign' => 'center', 'halign' => 'right']);
            $textcolor = $player->getTime(true) > 0 ? 'FFFF' : 'FFF5';
            $score = new Label($player->getTime(), ['width' => '22', 'textsize' => 3, 'valign' => 'center', 'padding-left' => 3, 'textcolor' => $textcolor]);

            $nickname = $player->NickName;

            if ($player->isOnline()) {
                if (isset($player->afk) && $player->afk == true) {
                    $nickname = '$n$o$e33afk$z ' . $nickname;
                }

                if ($player->isSpectator()) {
                    $nickname = '$eee📷$z ' . $nickname;
                }
            } else {
                $nickname = '$n$o$e33⌫$z ' . $nickname;
            }

            $nick = new Label($nickname, ['textsize' => 3, 'valign' => 'center', 'padding-left' => 2]);
            $builder->addRow($position, $score, $nick);

            $i++;
        }

        try {
            $builder->sendToAll();
        } catch (ParseException $e) {
            Log::error($e);
        }
    }
}