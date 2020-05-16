<?php

namespace EvoSC\Classes;


use EvoSC\Models\Player;
use Illuminate\Support\Collection;

/**
 * Class ManiaLinkEvent
 *
 * Handle actions send from ManiaScripts (clients).
 *
 * @package EvoSC\Classes
 */
class ManiaLinkEvent
{
    /**
     * @var Collection
     */
    private static Collection $maniaLinkEvents;

    /**
     * @var Collection
     */
    private static Collection $extendedMLE;

    public string $id;

    /**
     * @var callable
     */
    public $callback;
    public ?string $access;

    /**
     * Initialize ManiaLinkEvent
     */
    public static function init()
    {
        self::$maniaLinkEvents = collect();
        self::$extendedMLE = collect();

        Hook::add('PlayerManialinkPageAnswer', [self::class, 'call']);
        ManiaLinkEvent::add('mle', [self::class, 'maniaLinkExtended']);
    }

    /**
     * ManiaLinkEvent constructor.
     *
     * @param string $id
     * @param callable|array $callback
     * @param string $access
     */
    private function __construct(string $id, $callback, string $access = null)
    {
        $this->id = $id;
        $this->callback = $callback;
        $this->access = $access;
    }

    /**
     * Get all registered mania link events.
     *
     * @return Collection
     */
    private static function getManiaLinkEvents(): Collection
    {
        return self::$maniaLinkEvents;
    }

    /**
     * Add a manialink event. Callback must be of type [MyClass::class, 'methodToCall'].
     *
     * @param string $id
     * @param callable|array $callback
     * @param string|null $access
     */
    public static function add(string $id, $callback, string $access = null)
    {
        $maniaLinkEvents = self::getManiaLinkEvents();

        $event = new ManiaLinkEvent(strtolower($id), $callback, $access);

        $existingEvents = $maniaLinkEvents->where('id', strtolower($id));
        if ($existingEvents->isNotEmpty()) {
            self::$maniaLinkEvents = self::$maniaLinkEvents->diff($existingEvents);
        }

        $maniaLinkEvents->push($event);
    }

    public static function maniaLinkExtended(Player $player, $gameTime, $action, $i, $isFinished, ...$body)
    {
        if (!self::$extendedMLE->has($gameTime)) {
            self::$extendedMLE->put($gameTime, collect());
        }

        self::$extendedMLE->get($gameTime)->put($i, implode(',', $body));

        if ($isFinished == '1') {
            self::call($player, $action . ',' . self::$extendedMLE->get($gameTime)->implode(''));
            self::$extendedMLE->forget($gameTime);
        }
    }

    /**
     * Handle an ingoing mania-link event.
     *
     * @param Player $ply
     * @param string $action
     * @param array|null $formValues
     */
    public static function call(Player $ply, string $action, array $formValues = null)
    {
        $action = trim($action);

        if ($action == '') {
            return;
        }

        if (isVerbose()) {
            Log::write("$action", false);
        }

        if (preg_match('/(\w+[.\w]+)*(?:,[\d\w ]+)*/', $action, $matches)) {
            $event = self::getManiaLinkEvents()->where('id', $matches[1])->first();

            if (!$event) {
                Log::warning("Calling undefined ManiaLinkEvent $action.");

                return;
            }
        } else {
            Log::warning("Malformed ManiaLinkEvent $action.");

            return;
        }

        if ($event->access != null && !$ply->hasAccess($event->access)) {
            warningMessage('Sorry, you\'re not allowed to do that.')->send($ply);
            Log::write('Player ' . $ply . ' tried to access forbidden ManiaLinkEvent: ' . $event->id . ' -> ' . implode('::',
                    $event->callback));

            return;
        }

        $arguments = explode(',', $action);
        $arguments[0] = $ply;

        if ($formValues) {
            $formValuesObject = new \stdClass();
            foreach ($formValues as $value) {
                $formValuesObject->{$value['Name']} = $value['Value'];
            }
            array_push($arguments, $formValuesObject);
        }

        call_user_func_array($event->callback, $arguments);
    }

    public static function removeAll()
    {
        self::$maniaLinkEvents = collect();
    }

    public function __toString()
    {
        return $this->id . '(' . serialize($this->callback) . ')';
    }
}