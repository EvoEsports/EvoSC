<?php

namespace esc\controllers;


use esc\classes\Log;
use esc\classes\Manialink;
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
                $player = Player::findOrFail($info['Login']);
                $player->update($info);
            } catch (\Exception $e) {
                $player = Player::create($info);
            }

            if($player){
                $player->setIsSpectator($info['SpectatorStatus']);
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
        $manialink = new Manialink(-160, 90, "LiveRanking", 1);
        $manialink->addQuad(0, 0, 50, 80, '0005', -1);
        $manialink->addLabel(3, -3, 50, 10, "\$mPlayers", 0.7);

        $players = self::getPlayers()->sortBy('spectator')->sortBy('score');

        $row = 0;
        foreach ($players as $player) {
            $manialink->addLabel(3, -($row * 5 + 8), 50, 10, $player->nick(), 0.6);
            $manialink->addLabel(45, -($row * 5 + 8), 50, 10, $player->getTime(), 0.6, 0, 'right');
            $row++;
        }

        //Do not send identical manialinks more than once, reduce network traffic
        $hash = md5(serialize($manialink));
        if (self::$lastManialinkHash != $hash) {
            $manialink->sendToAll();
            self::$lastManialinkHash = $hash;
        }else{
            Log::info("Manialink identical, not sending.");
        }
    }
}