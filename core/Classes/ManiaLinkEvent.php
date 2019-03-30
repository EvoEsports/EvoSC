<?php

namespace esc\Classes;


use esc\Models\Player;
use Illuminate\Support\Collection;

/**
 * Class ManiaLinkEvent
 *
 * Handle actions send from ManiaScripts (clients).
 *
 * @package esc\Classes
 */
class ManiaLinkEvent
{
    /**
     * @var Collection
     */
    private static $maniaLinkEvents;

    public $id;
    public $callback;
    public $access;

    /**
     * Initialize ManiaLinkEvent
     */
    public static function init()
    {
        self::$maniaLinkEvents = new Collection();

        Hook::add('PlayerManialinkPageAnswer', [self::class, 'call']);
    }

    /**
     * ManiaLinkEvent constructor.
     *
     * @param string      $id
     * @param array       $callback
     * @param string|null $access
     */
    private function __construct(string $id, array $callback, string $access = null)
    {
        $this->id       = $id;
        $this->callback = $callback;
        $this->access   = $access;
    }

    /**
     * Get all registered mania link events.
     *
     * @return \Illuminate\Support\Collection
     */
    private static function getManiaLinkEvents(): Collection
    {
        return self::$maniaLinkEvents;
    }

    /**
     * Add a manialink event. Callback must be of type [MyClass::class, 'methodToCall'].
     *
     * @param string      $id
     * @param array       $callback
     * @param string|null $access
     */
    public static function add(string $id, array $callback, string $access = null)
    {
        $maniaLinkEvents = self::getManiaLinkEvents();

        $event = new ManiaLinkEvent(strtolower($id), $callback, $access);

        $existingEvents = $maniaLinkEvents->where('id', strtolower($id));
        if ($existingEvents->isNotEmpty()) {
            self::$maniaLinkEvents = self::$maniaLinkEvents->diff($existingEvents);
        }

        $maniaLinkEvents->push($event);
    }

    /**
     * Handle an ingoing mania-link event.
     *
     * @param \esc\Models\Player $ply
     * @param string             $action
     */
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

        if ($event->access != null && !$ply->hasAccess($event->access)) {
            warningMessage('Access denied.')->send($ply);
            Log::logAddLine('Access', 'Player ' . $ply . ' tried to access forbidden ManiaLinkEvent: ' . $event->id . ' -> ' . implode('::', $event->callback));

            return;
        }

        if (strlen($event->id) < strlen($action)) {
            $arguments    = explode(',', $action);
            $arguments[0] = $ply;
            call_user_func_array($event->callback, $arguments);

            return;
        }

        call_user_func($event->callback, $ply);
    }
}