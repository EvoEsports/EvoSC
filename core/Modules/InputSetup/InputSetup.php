<?php

namespace EvoSC\Modules\InputSetup;


use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Exceptions\InvalidArgumentException;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\QuickButtons\QuickButtons;
use Exception;
use Illuminate\Support\Collection;

class InputSetup extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static Collection $binds;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'sendBackgroundScript']);

        ManiaLinkEvent::add('show_key_bind_settings', [self::class, 'showSettings']);
        ManiaLinkEvent::add('bound_key_pressed', [self::class, 'keyPressed']);
        ManiaLinkEvent::add('update_keybinds', [self::class, 'sendBackgroundScript']);
        ManiaLinkEvent::add('update_bind', [self::class, 'updateBind']);

        QuickButtons::addButton('🎮', 'Input-Setup', 'show_key_bind_settings');
    }

    /**
     * @param Player $player
     * @throws InvalidArgumentException
     */
    public static function showSettings(Player $player)
    {
        Template::show($player, 'InputSetup.settings', [
            'binds' => self::getPlayerKeyBinds($player)
        ]);
    }

    /**
     * Send the key-bind script to the player
     *
     * @param Player $player
     * @throws InvalidArgumentException
     */
    public static function sendBackgroundScript(Player $player)
    {
        Template::show($player, 'InputSetup.script', [
            'binds' => self::getPlayerKeyBinds($player)
        ]);
    }

    /**
     * Add a new key-bind
     *
     * @param string $id
     * @param string $description
     * @param callable $callback
     * @param string $defaultKey
     * @param string|null $access
     */
    public static function add(string $id, string $description, $callback, string $defaultKey, string $access = null)
    {
        if (!self::$binds) {
            self::$binds = collect();
        }

        self::$binds->push([
            'id' => $id,
            'description' => $description,
            'callback' => $callback,
            'default' => $defaultKey,
            'access' => $access,
        ]);
    }

    /**
     * Update a key-bind
     *
     * @param Player $player
     * @param mixed ...$data
     */
    public static function updateBind(Player $player, ...$data)
    {
        $binds = DB::table('user-settings')
            ->where('player_Login', '=', $player->Login)
            ->where('name', '=', 'key-binds')
            ->first();

        if (!$binds) {
            $binds = collect();
        } else {
            $binds = collect(json_decode($binds->value));
        }

        $data = json_decode(implode(',', $data)); //new bind data

        if ($binds->isNotEmpty()) {
            $binds = $binds->where('id', '!=', $data->id); //get rid of old bind
        }

        $binds->push($data); //push new bind

        DB::table('user-settings')
            ->updateOrInsert([
                'player_Login' => $player->Login,
                'name' => 'key-binds'
            ], [
                'value' => $binds->toJson()
            ]);
    }

    /**
     * @param Player $player
     * @return Collection
     */
    private static function getPlayerKeyBinds(Player $player): Collection
    {
        $userBinds = $player->setting('key-binds', true);
        $userBinds = collect($userBinds)->keyBy('id');

        return self::$binds->map(function ($bind) use ($player, $userBinds) {
            if ($bind['access'] && !$player->hasAccess($bind['access'])) {
                return null;
            }

            $keyBindData = [
                'id' => $bind['id'],
                'name' => $bind['default'],
                'default' => $bind['default'],
                'description' => $bind['description']
            ];

            if ($userBinds->has($bind['id'])) {
                $b = $userBinds->get($bind['id']);

                if (property_exists($b, 'name') && !empty($b->name)) {
                    $keyBindData['name'] = $b->name;
                }
            }

            return $keyBindData;
        })
            ->filter()
            ->values();
    }

    /**
     * Handle bound key presses
     *
     * @param Player $player
     * @param string $id
     * @throws Exception
     */
    public static function keyPressed(Player $player, string $id)
    {
        self::$binds->where('id', $id)
            ->each(function ($bind) use ($player) {
                if ($bind['access']) {
                    if (!$player->hasAccess($bind['access'])) {
                        return;
                    }
                }

                if (gettype($bind['callback']) == "object") {
                    $func = $bind['callback'];
                    $func($player);
                } else {
                    if (is_callable($bind['callback'], false, $callableName)) {
                        Log::write("Execute: " . $bind['callback'][0] . " " . $bind['callback'][1], isVeryVerbose());
                        call_user_func($bind['callback'], $player);
                    } else {
                        throw new Exception("KeyBind callback invalid, must use: [ClassName, ClassFunctionName] or Closure");
                    }
                }
            });
    }

    /**
     * @return void
     */
    public static function clearAll()
    {
        self::$binds = collect();
    }
}