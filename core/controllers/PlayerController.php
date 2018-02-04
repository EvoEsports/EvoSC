<?php

namespace esc\controllers;


use esc\classes\Hook;
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
//        Hook::add('PlayerConnect', '\esc\controllers\PlayerController::playerConnect');
        Hook::add('PlayerDisconnect', '\esc\controllers\PlayerController::playerDisconnect');
        Hook::add('PlayerFinish', '\esc\controllers\PlayerController::playerFinish');

        self::$players = new Collection();
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

        self::sendScoreboard();

        return $player;
    }

    public static function playerFinish(Player $player, $score)
    {
        if ($score > 0) {
            $player->setScore($score);
            Log::info($player->nick() . " finished with time ($score) " . $player->getTime());
            self::sendScoreboard();
        }
    }

    public static function playerDisconnect(Player $player, $disconnectReason)
    {
        Log::info($player->nick(true) . " left the server [$disconnectReason].");
        $player->setOffline();
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

            if(!$player){
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
            }

            $player->update($info);
            $player->setIsSpectator($info['SpectatorStatus'] > 0);
        }

        self::sendScoreboard();
    }

    public static function getPlayerByLogin(string $login): ?Player
    {
        echo "GET: $login ";
        $player = self::getPlayers()->where('Login', $login)->first();
        echo "FOUND: (" . $player->Login . ") " . $player->nick(true) . "\n";
        return $player;
    }

    public static function sendScoreboard()
    {
        //create template
        $builder = new ManiaBuilder('LiveRankings', ManiaBuilder::STICK_LEFT, ManiaBuilder::STICK_TOP, 60, 80);

        $title = new Row(2);
        $title->setElement(Label::create('Live rankings', 0.8));
        $title->setBackground('0008');
        $builder->addRow($title);

        $players = self::getPlayers()->filter(function (Player $player, $key) {
            return $player->score > 0;
        });

        $notFinished = self::getPlayers()->filter(function (Player $player, $key) {
            return $player->score == 0;
        });

        $i = 1;
        foreach([$players, $notFinished] as $collection){
            foreach ($collection as $player) {
                $nick = $player->nick();
                $time = $player->getTime();

                $ply = new Row(2);
                $position = "$i.";
                if ($player->isSpectator()) {
                    $position = "📷";
                }
                $ply->setElement(Label::create("$position   $time   $nick", 0.6));
                $ply->setBackground('0005');

                $builder->addRow($ply);
                $i++;
            }
        }

        $builder->sendToAll();
    }
}