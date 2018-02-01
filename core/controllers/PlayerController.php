<?php

namespace esc\controllers;


use esc\classes\Log;
use esc\classes\Manialink;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;

class PlayerController
{
    private static $players;

    public static function initialize()
    {
        HookController::add('ManiaPlanet.PlayerConnect', '\esc\controllers\PlayerController::playerConnect');
        HookController::add('ManiaPlanet.PlayerDisconnect', '\esc\controllers\PlayerController::playerDisconnect');
        HookController::add('ManiaPlanet.PlayerInfoChanged', '\esc\controllers\PlayerController::playerInfoChanged');
        HookController::add('TrackMania.PlayerFinish', '\esc\controllers\PlayerController::playerFinish');

        self::$players = new Collection();
    }

    private static function getPlayers(): Collection
    {
        return self::$players;
    }

    public static function playerConnect($login): Player
    {
        try {
            $player = Player::whereLogin($login)->firstOrFail();
        } catch (\Exception $e) {
            $player = new Player();
            $player->login = $login;
            $player->save();
        }

        $player->increment('Visits');

        self::getPlayers()->add($player);
        Log::info($player->nick(true) . " ($login) joined the server.");

        self::sendScoreboard();

        return $player;
    }

    public static function playerFinish($uid, $login, $score)
    {
        if($score > 0){
            $player = self::getPlayerByLogin($login);

            if(!$player){
                return;
            }

            $player->setScore($score);
            self::sendScoreboard();
        }
    }

    public static function playerDisconnect($login, $disconnectReason)
    {
        $player = self::getPlayerByLogin($login);

        if($player){
            Log::info($player->nick(true) . " ($login) left the server.");
            self::$players = self::getPlayers()->diff([$player]);
        }
    }

    public static function playerInfoChanged($infoplayerInfo)
    {
        $player = self::getPlayerByLogin($infoplayerInfo['Login']);

        if($player){
            $player->update($infoplayerInfo);
        }
    }

    public static function getPlayerByLogin(string $login): ?Player
    {
        $playersFound = self::getPlayers()->filter(function ($value, $key) use ($login) {
            return $value->Login = $login;
        });

        if($playersFound->isNotEmpty() && $playersFound->count() == 1){
            return $playersFound->first();
        }

        Log::warning("Player not found ($login).");

        return null;
    }

    public static function sendScoreboard(){
        $manialink = new Manialink(-160, 90, "LiveRanking", 1);
        $manialink->addQuad(0, 0, 50, 80, '0003', -1);
        $manialink->addLabel(3, -3, 50,10, "\$mPlayers", 0.7);

        $row = 0;
        foreach (self::getPlayers() as $player) {
            $manialink->addLabel(3, -($row * 3.5 + 8), 50, 10, $player->nick(), 0.6);
            $manialink->addLabel(45, -($row * 3.5 + 8), 50, 10, $player->getTime(), 0.6, 0, 'right');
            $row++;
        }

        $manialink->sendToAll();
    }
}