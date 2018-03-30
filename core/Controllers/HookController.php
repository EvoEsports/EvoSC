<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ModescriptCallbacks;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Database\Eloquent\Collection;

class HookController extends ModescriptCallbacks
{
    private static $hooks;

    private static $eventMap = [
        'ManiaPlanet.PlayerConnect' => 'PlayerConnect',
        'ManiaPlanet.PlayerDisconnect' => 'PlayerDisconnect',
        'ManiaPlanet.PlayerInfoChanged' => 'PlayerInfoChanged',
        'ManiaPlanet.PlayerChat' => 'PlayerChat',
        'ManiaPlanet.BeginMap' => 'BeginMap',
        'ManiaPlanet.EndMap' => 'EndMap',
        'ManiaPlanet.EndMatch' => 'EndMatch',
        'ManiaPlanet.BeginMatch' => 'BeginMatch',
        'TrackMania.PlayerCheckpoint' => 'PlayerCheckpoint',
        'TrackMania.PlayerFinish' => 'PlayerFinish',
        'TrackMania.PlayerIncoherence' => 'PlayerIncoherence',
        'ManiaPlanet.PlayerManialinkPageAnswer' => 'PlayerManialinkPageAnswer',
        'ManiaPlanet.ModeScriptCallbackArray' => 'ManiaPlanet.ModeScriptCallbackArray',
    ];

    public static function init()
    {
        self::$hooks = new Collection();
    }

    static function getHooks(string $hook = null): ?Collection
    {
        if ($hook) {
            return self::$hooks->filter(function ($value, $key) use ($hook) {
                return $value->getEvent() == $hook;
            });
        }

        return self::$hooks;
    }

    public static function add(string $event, string $staticFunction)
    {
        $hooks = self::getHooks();
        $hook = new Hook($event, $staticFunction);

        if ($hooks) {
            self::getHooks()->add($hook);
            Log::logAddLine('Hook', "Added $event -> $staticFunction", false);
        }
    }

    static function fireHookBatch($hooks, ...$arguments)
    {
        foreach ($hooks as $hook) {
            $hook->execute(...$arguments);
        }
    }

    private static function handleModeScriptCallbackArray(array $modescriptCallbackArray)
    {
        $callback = $modescriptCallbackArray[0];
        $arguments = $modescriptCallbackArray[1];

        switch ($callback) {
            case 'Trackmania.Scores':
                self::tmScores($arguments);
                break;

            case 'Trackmania.Event.GiveUp':
                self::tmGiveUp($arguments);
                break;

            case 'Trackmania.Event.WayPoint':
                self::tmWayPoint($arguments);
                break;

            case 'Trackmania.Event.StartCountdown':
                self::tmStartCountdown($arguments);
                break;

            case 'Trackmania.Event.PlayerStartLine':
                self::tmStartLine($arguments);
                break;

            case 'Trackmania.Event.Stunt':
                self::tmStunt($arguments);
                break;

            default:
                Log::logAddLine('ScriptCallback', "Calling unhandled $callback");
                break;
        }
    }

    public static function fire(string $hook, $arguments = null)
    {
        Log::logAddLine('Hook', "Called: $hook", false);

        if($hook == 'ManiaPlanet.ModeScriptCallbackArray'){
            //handle modescript callbacks
            self::handleModeScriptCallbackArray($arguments);
            return;
        }

        //handle maniaplanet callbacks
        $hooks = self::getHooks($hook);
        switch ($hook) {
            case 'BeginMap':
                //SMapInfo Map
                $map = Map::where('FileName', $arguments[0]['FileName'])->first();
                self::fireHookBatch($hooks, $map);
                break;

            case 'EndMap':
                //SMapInfo Map
                $map = Map::where('FileName', $arguments[0]['FileName'])->first();
                self::fireHookBatch($hooks, $map);
                break;

            case 'BeginMatch':
                //SMapInfo Map
                self::fireHookBatch($hooks);
                break;

            case 'EndMatch':
                //SMapInfo Map
                self::fireHookBatch($hooks, $arguments[0], $arguments[1]);
                break;

            case 'PlayerInfoChanged':
                //SPlayerInfo PlayerInfo
                PlayerController::playerInfoChanged($arguments);
                $players = new Collection();
                foreach ($arguments as $playerInfo) {
                    $players->add(Player::find($playerInfo['Login']));
                }
                self::fireHookBatch($hooks, $players);
                break;

            case 'PlayerConnect':
                //string Login, bool IsSpectator
                if (Player::whereLogin($arguments[0])->get()->isEmpty()) {
                    $player = Player::create(['Login' => $arguments[0]]);
                } else {
                    $player = Player::find($arguments[0]);
                }

                $player->spectator = $arguments[1];
                self::fireHookBatch($hooks, $player);
                break;

            case 'PlayerDisconnect':
                //string Login, string DisconnectionReason
                $player = Player::find($arguments[0]);
                self::fireHookBatch($hooks, $player, $arguments[1]);
                break;

            case 'PlayerChat':
                //int PlayerUid, string Login, string Text, bool IsRegistredCmd
                $player = Player::find($arguments[1]);
                self::fireHookBatch($hooks, $player, $arguments[2], $arguments[3]);
                break;

//            case 'PlayerCheckpoint':
//                //int PlayerUid, string Login, int TimeOrScore, int CurLap, int CheckpointIndex
//                $player = Player::find($arguments[1]);
//                self::fireHookBatch($hooks, $player, $arguments[2], $arguments[3], $arguments[4]);
//                break;

//            case 'PlayerFinish':
//                //int PlayerUid, string Login, int TimeOrScore
//                $player = Player::find($arguments[1]);
//                if ($player == null) {
//                    $player = Player::find($arguments[1]);
//                }
//                self::fireHookBatch($hooks, $player, $arguments[2]);
//                break;

            case 'PlayerIncoherence':
                //int PlayerUid, string Login
                $player = Player::find($arguments['Login']);
                self::fireHookBatch($hooks, $player);
                break;

            case 'PlayerManialinkPageAnswer':
                //int PlayerUid, string Login, string Answer, SEntryVal Entries[]
                $player = Player::find($arguments[1]);
                self::fireHookBatch($hooks, $player, $arguments[2]);
                break;
        }
    }

    public static function call($event, $arguments = null)
    {
//        Log::logAddLine('RPC-Event', $event, true);

        if (array_key_exists($event, self::$eventMap)) {
            foreach ($arguments as $key => $argument) {
                if (is_null($argument)) {
                    Log::logAddLine('RPC-Event', 'Calling event ' . $event . ' with null argument: ' . $key, true);
                    return;
                }
            }

            $hook = self::$eventMap[$event];
            self::fire($hook, $arguments);
        } else {
            Log::logAddLine('RPC-Event', 'Calling unhandled ' . $event, true);
        }
    }

    /**
     * Handle the fetched callbacks
     * @param $callbacks
     */
    public static function handleCallbacks($callbacks)
    {
        foreach ($callbacks as $callback) {
            if (count($callback) == 2) {
                self::call($callback[0], $callback[1]);
            } else {
                echo "Got faulty rpc-callback: ";
                var_dump($callback);
            }
        }
    }
}