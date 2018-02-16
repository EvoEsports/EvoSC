<?php

namespace esc\classes;


use esc\models\Player;
use Illuminate\Support\Collection;

class ManiaLinkEvent
{
    private static $maniaLinkEvents;

    public $id;
    public $callback;

    private function __construct(string $id, string $callback)
    {
        $this->id = $id;
        $this->callback = $callback;
    }

    private static function getManiaLinkEvents(): Collection
    {
        return self::$maniaLinkEvents;
    }

    public static function init()
    {
        self::$maniaLinkEvents = new Collection();

        Hook::add('PlayerManialinkPageAnswer', '\esc\classes\ManiaLinkEvent::call');
    }

    public static function add(string $id, string $callback)
    {
        $maniaLinkEvents = self::getManiaLinkEvents();

        if ($maniaLinkEvents->where('id', $id)->isNotEmpty()) {
            Log::error("ManiaLinkEvent with id '$id' already exists.");
        }

        $event = new ManiaLinkEvent($id, $callback);
        $maniaLinkEvents->push($event);
    }

    public static function call(Player $ply, string $action)
    {
        if (preg_match('/(\w+[\.\w]+)*(?:,[\d\w ]+)*/', $action, $matches)) {
            $event = self::getManiaLinkEvents()->where('id', $matches[1])->first();

            if (!$event) {
                Log::warning("Calling non-existent ManiaLinkEvent $action.");
                return;
            }
        } else {
            Log::warning("Malformed ManiaLinkEvent $action.");
            return;
        }

        if (strlen($event->id) < strlen($action)) {
            $arguments = explode(',', $action);
            $arguments[0] = $ply;
            call_user_func_array($event->callback, $arguments);
            return;
        }

        call_user_func($event->callback, $ply);
    }
}