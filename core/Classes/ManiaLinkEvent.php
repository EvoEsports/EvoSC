<?php

namespace esc\Classes;


use esc\Controllers\ChatController;
use esc\Models\Player;
use Illuminate\Support\Collection;

class ManiaLinkEvent
{
    private static $maniaLinkEvents;

    public $id;
    public $callback;
    public $access;

    private function __construct(string $id, string $callback, string $access = null)
    {
        $this->id = $id;
        $this->callback = $callback;
        $this->access = $access;
    }

    private static function getManiaLinkEvents(): Collection
    {
        return self::$maniaLinkEvents;
    }

    public static function init()
    {
        self::$maniaLinkEvents = new Collection();

        Hook::add('PlayerManialinkPageAnswer', '\esc\Classes\ManiaLinkEvent::call');
    }

    public static function add(string $id, string $callback, string $access = null)
    {
        $maniaLinkEvents = self::getManiaLinkEvents();

        $event = new ManiaLinkEvent($id, $callback, $access);

        $existingEvents = $maniaLinkEvents->where('id', $id);
        if ($existingEvents->isNotEmpty()) {
            self::$maniaLinkEvents = self::$maniaLinkEvents->diff($existingEvents);
        }

        $maniaLinkEvents->push($event);
    }

    public static function call(Player $ply, string $action)
    {
        Log::logAddLine('Mania Link Event', "$action", false);

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

        if ($event->access && !$ply->group->hasAccess($event->access)) {
            ChatController::message($ply, '_warning', 'Access denied');
            Log::logAddLine('Access', 'Player ' . stripAll($ply->NickName) . ' tried to access forbidden: ' . $event->callback);
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