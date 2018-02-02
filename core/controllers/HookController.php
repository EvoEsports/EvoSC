<?php

namespace esc\controllers;


use esc\classes\Hook;
use esc\classes\Log;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;

class HookController
{
    private static $hooks;

    private static $eventMap = [
        'ManiaPlanet.PlayerConnect' => 'PlayerConnect',
        'ManiaPlanet.PlayerDisconnect' => 'PlayerDisconnect',
        'ManiaPlanet.PlayerInfoChanged' => 'PlayerInfoChanged',
        'TrackMania.PlayerFinish' => 'PlayerFinish',
        'ManiaPlanet.PlayerChat' => 'PlayerChat'
    ];

    public static function initialize()
    {
        self::$hooks = new Collection();
    }

    private static function getHooks(): ?Collection
    {
        return self::$hooks;
    }

    public static function add(string $event, string $staticFunction)
    {
        $hooks = self::getHooks();
        $hook = new Hook($event, $staticFunction);

        if ($hooks) {
            self::getHooks()->add($hook);
            Log::hook("Added $event -> $staticFunction");
        }
    }

    private static function fireHookBatch($hooks, ...$arguments)
    {
        foreach ($hooks as $hook) {
            $hook->execute(...$arguments);
        }
    }

    private static function fire(string $hook, $arguments = null)
    {
        echo "Hook called: $hook\n";

        $hooks = self::getHooks()->filter(function ($value, $key) use ($hook) {
            return $value->getEvent() == $hook;
        });

        switch ($hook) {
            case 'PlayerConnect':
                try {
                    $player = Player::whereLogin($arguments[0])->firstOrFail();
                } catch (\Exception $e) {
                    $player = new Player();
                    $player->login = $arguments[0];
                    $player->saveOrFail();
                }
                $player->spectator = $arguments[1];
                self::fireHookBatch($hooks, $player);
                break;

            case 'PlayerDisconnect':
                $player = PlayerController::getPlayerByLogin($arguments[0]);
                self::fireHookBatch($hooks, $player, $arguments[1]);
                break;

            case 'PlayerInfoChanged':
                self::fireHookBatch($hooks, $arguments);
                break;

            case 'PlayerFinish':
                $player = PlayerController::getPlayerByLogin($arguments[1]);
                if($player == null){
                    $player = Player::whereLogin($arguments[1])->first();
                }
                self::fireHookBatch($hooks, $player, $arguments[2]);
                break;

            case 'ManiaPlanet.PlayerChat':
                self::fireHookBatch($hooks, $arguments);
                break;
        }
    }

    public static function call($event, $arguments = null)
    {
        echo "Event called: $event\n";

        if (array_key_exists($event, self::$eventMap)) {
            $hook = self::$eventMap[$event];
            self::fire($hook, $arguments);
        }
    }

    public static function handleCallbacks($callbacks)
    {
        foreach ($callbacks as $callback) {
            self::call($callback[0], $callback[1]);
        }
    }
}