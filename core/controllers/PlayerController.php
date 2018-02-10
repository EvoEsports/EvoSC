<?php

namespace esc\controllers;


use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\ManiaBuilder;
use esc\ManiaLink\Elements\Label;
use esc\ManiaLink\ManiaStyle;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\ParseException;

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
                self::$players = self::getPlayers()->add($player)->unique();
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
        if ($player) {
            echo "FOUND: (" . $player->Login . ") " . $player->nick(true) . "\n";
        } else {
            echo "Player not found.\n";
        }
        return $player;
    }

    public static function sendScoreboard()
    {
        $builder = new ManiaBuilder('LiveRankings', ManiaBuilder::STICK_LEFT, ManiaBuilder::STICK_TOP, 70, 80, .6, ['padding' => 3, 'bgcolor' => '0006']);

        $label = new Label("Playerlist", ['width' => '30', 'textsize' => 5, 'height' => 12]);
        $builder->addRow($label);

        foreach (self::getPlayers() as $index => $player) {
            $position = new Label(($index + 1) . '.', ['width' => 8, 'textsize' => 3, 'valign' => 'center', 'halign' => 'right']);
            $textcolor = $player->getTime(true) > 0 ? 'FFFF' : 'FFF5';
            $score = new Label($player->getTime(), ['width' => '22', 'textsize' => 3, 'valign' => 'center', 'padding-left' => 3, 'textcolor' => $textcolor]);
            $nick = new Label($player->NickName, ['textsize' => 3, 'valign' => 'center', 'padding-left' => 2]);
            $builder->addRow($position, $score, $nick);
        }

        try {
            $builder->sendToAll();
        } catch (ParseException $e) {
            Log::error($e);
        }
    }
}