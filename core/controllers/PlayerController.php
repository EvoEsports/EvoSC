<?php

namespace esc\controllers;


use esc\classes\Log;
use esc\classes\ManiaBuilder;
use esc\ManiaLink\Label;
use esc\ManiaLink\Row;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;

class PlayerController
{
    private static $players;
    private static $lastManialinkHash;

    public static function initialize()
    {
        HookController::add('PlayerConnect', '\esc\controllers\PlayerController::playerConnect');
        HookController::add('PlayerDisconnect', '\esc\controllers\PlayerController::playerDisconnect');
        HookController::add('PlayerFinish', '\esc\controllers\PlayerController::playerFinish');

        self::$players = new Collection();
    }

    public static function getPlayers(): Collection
    {
        return self::$players;
    }

    public static function playerConnect(Player $player): Player
    {
        $player->increment('Visits');

        self::getPlayers()->add($player);
        Log::info($player->nick(true) . " joined the server.");

        self::sendScoreboard();

        return $player;
    }

    public static function playerFinish(Player $player, $score)
    {
        if ($score > 0) {
            $player->setScore($score);
            self::sendScoreboard();
        }
    }

    public static function playerDisconnect(Player $player, $disconnectReason)
    {
        Log::info($player->nick(true) . " left the server [$disconnectReason].");
        self::$players = self::getPlayers()->diff([$player]);
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
            try {
                $player = self::getPlayerByLogin($info['Login']);
            } catch (\Exception $e) {
                $player = Player::create($info);
                self::$players->add($player);
            }

            if ($player) {
                $player->update($info);
                $player->setIsSpectator($info['SpectatorStatus'] > 0);
            }
        }

        self::sendScoreboard();
    }

    public static function getPlayerByLogin(string $login): ?Player
    {
        $playersFound = self::getPlayers()->filter(function ($value, $key) use ($login) {
            return $value->Login == $login;
        });

        return $playersFound->first();
    }

    public static function sendScoreboard()
    {
        $builder = new ManiaBuilder('LiveScore', ManiaBuilder::STICK_LEFT, ManiaBuilder::STICK_TOP, 60, 80);

        $title = new Row(2);
        $title->setElement(Label::create('Scoreboard', 0.8));
        $title->setBackground('0008');
        $builder->addRow($title);

        $i = 1;
        foreach (self::getPlayers()->sortBy('score') as $player) {
            $nick = $player->nick();
            $time = $player->getTime();

            $ply = new Row(2);
            $position = "$i.";
            if($player->isSpectator()){
                $position = "📷";
            }
            $ply->setElement(Label::create("$position   $time   $nick", 0.6));
            $ply->setBackground('0005');

            $builder->addRow($ply);
            $i++;
        }

        //Do not send identical manialinks more than once, reduce network traffic
        $hash = md5(serialize($builder));
        if (self::$lastManialinkHash != $hash) {
            $builder->sendToAll();
            self::$lastManialinkHash = $hash;
        } else {
            Log::info("Manialink identical, not sending.");
        }
    }
}