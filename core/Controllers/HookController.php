<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ModescriptCallbacks;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Database\Eloquent\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

class HookController extends ModescriptCallbacks
{
    private static $hooks;

    private static $eventMap = [
        'PlayerConnect'                         => 'PlayerConnect',
        'ManiaPlanet.PlayerDisconnect'          => 'PlayerDisconnect',
        'ManiaPlanet.PlayerInfoChanged'         => 'PlayerInfoChanged',
        'ManiaPlanet.PlayerChat'                => 'PlayerChat',
        'ManiaPlanet.BeginMap'                  => 'BeginMap',
        'ManiaPlanet.EndMap'                    => 'EndMap',
        'ManiaPlanet.EndMatch'                  => 'EndMatch',
        'ManiaPlanet.BeginMatch'                => 'BeginMatch',
        'TrackMania.PlayerCheckpoint'           => 'PlayerCheckpoint',
        'TrackMania.PlayerFinish'               => 'PlayerFinish',
        'TrackMania.PlayerIncoherence'          => 'PlayerIncoherence',
        'ManiaPlanet.PlayerManialinkPageAnswer' => 'PlayerManialinkPageAnswer',
        'ManiaPlanet.ModeScriptCallbackArray'   => 'ManiaPlanet.ModeScriptCallbackArray',
        'PlayerLocal'                           => 'PlayerLocal',
        //        'PlayerRateMap' => 'PlayerRateMap',
        //        'PlayerDonate' => 'PlayerDonate',
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
        $hook  = new Hook($event, $staticFunction);

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
        $callback  = $modescriptCallbackArray[0];
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

            case 'Trackmania.Event.StartLine':
                self::tmStartLine($arguments);
                break;

            case 'Trackmania.Event.Stunt':
                self::tmStunt($arguments);
                break;

            case 'Trackmania.Event.OnPlayerAdded':
                self::tmPlayerConnect($arguments);
                break;

            case 'Trackmania.Event.OnPlayerRemoved':
                self::tmPlayerLeave($arguments);
                break;

            default:
                Log::logAddLine('ScriptCallback', "Calling unhandled $callback", false);
                break;
        }
    }
}