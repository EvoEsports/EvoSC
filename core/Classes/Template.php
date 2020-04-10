<?php

namespace esc\Classes;


use esc\Controllers\TemplateController;
use esc\Models\Player;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

/**
 * Class Template
 *
 * Render manialink-templates and send them to all or single players.
 *
 * @package esc\Classes
 */
class Template
{
    public string $index;
    public string $template;

    /**
     * @var Collection
     */
    private static Collection $multiCalls;

    /**
     * Template constructor.
     *
     * @param  string  $index
     * @param  string  $template
     */
    public function __construct(string $index, string $template)
    {
        $this->index = $index;
        $this->template = $template;
    }

    /**
     * Show the template to everyone.
     *
     * @param string $index
     * @param array|null $values
     * @param int $timeoutInSeconds
     */
    public static function showAll(string $index, array $values = [], int $timeoutInSeconds = 0)
    {
        global $__ManiaPlanet;
        $values['is_maniaplanet'] = $__ManiaPlanet;
        $xml = TemplateController::getTemplate($index, $values);
        Server::sendDisplayManialinkPage('', $xml, $timeoutInSeconds * 1000);
    }

    /**
     * Render and send a template to a player.
     *
     * @param Player $player
     * @param string $index
     * @param null $values
     * @param bool $multicall
     * @param int $timeoutInSeconds
     */
    public static function show(Player $player, string $index, $values = null, bool $multicall = false, int $timeoutInSeconds = 0)
    {
        global $__ManiaPlanet;
        $data = [];

        if ($values instanceof Collection) {
            foreach ($values as $key => $value) {
                $data[$key] = $value;
            }
        } else {
            $data = $values;
        }

        $data['localPlayer'] = $player;
        $data['is_maniaplanet'] = $__ManiaPlanet;
        $xml = TemplateController::getTemplate($index, $data);

        if ($xml != '') {
            if ($multicall) {
                if (!isset(self::$multiCalls)) {
                    self::$multiCalls = collect();
                }

                self::$multiCalls->put($player->Login, $xml);
            } else {
                Server::sendDisplayManialinkPage($player->Login, $xml, $timeoutInSeconds * 1000);
            }
        }
    }

    /**
     * Hide a manialink with the given id for everyone.
     *
     * @param  string  $id
     */
    public static function hideAll(string $id)
    {
        Server::sendDisplayManialinkPage('', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<manialink version="3" name="ESC:'.$id.'" id="'.$id.'"></manialink>');
    }

    /**
     * Hide a manialink with the given id for a single player.
     *
     * @param Player $player
     * @param string $id
     */
    public static function hide(Player $player, string $id)
    {
        Server::sendDisplayManialinkPage($player->Login, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<manialink version="3" name="ESC:'.$id.'" id="'.$id.'"></manialink>');
    }

    /**
     * Send all collected templates.
     */
    public static function executeMulticall()
    {
        if (!isset(self::$multiCalls)) {
            return;
        }

        self::$multiCalls->each(function ($xml, $login) {
            try {
                Server::sendDisplayManialinkPage($login, $xml, 0, false, true);
            } catch (Exception $e) {
                $id = uniqid(evo_str_slug($e->getMessage())).'.xml';
                Log::warning('Failed to render template for '.$login.'. Saving to as '.$id);
                Cache::put($id, $xml);
            }
        });

        self::$multiCalls = collect();

        Server::executeMulticall();
    }

    /**
     * Render the template and get the xml as string.
     *
     * @param  string  $index
     * @param  array|null  $values
     *
     * @return string
     */
    public static function toString(string $index, array $values = null): string
    {
        if (!$values) {
            $values = [];
        }

        return TemplateController::getTemplate($index, $values);
    }
}