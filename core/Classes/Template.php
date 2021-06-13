<?php

namespace EvoSC\Classes;


use EvoSC\Controllers\TemplateController;
use EvoSC\Exceptions\InvalidArgumentException;
use EvoSC\Models\Player;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class Template
 *
 * Render manialink-templates and send them to all or single players.
 *
 * @package EvoSC\Classes
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
     * @param string $index
     * @param string $template
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
     * @param Player|string $player
     * @param string $index
     * @param Collection|array|null $values
     * @param bool $multicall
     * @param int $timeoutInSeconds
     * @throws InvalidArgumentException
     */
    public static function show($player, string $index, $values = null, bool $multicall = false, int $timeoutInSeconds = 0)
    {
        global $__ManiaPlanet;

        if ($values instanceof Collection) {
            $data = $values->toArray();
        } else if (is_array($values)) {
            $data = $values;
        } else if (is_null($values)) {
            $data = [];
        } else {
            throw new InvalidArgumentException('Values must be of type Collection or array');
        }

        list($childClass, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $data['localPlayer'] = $player;
        $data['is_maniaplanet'] = $__ManiaPlanet;
        $data['MODULE'] = $caller['class'];
        $xml = TemplateController::getTemplate($index, $data);

        $playerLogin = $player;
        if($player instanceof Player){
            $playerLogin = $player->Login;
        }

        if ($xml != '') {
            if ($multicall) {
                if (!isset(self::$multiCalls)) {
                    self::$multiCalls = collect();
                }

                self::$multiCalls->put($playerLogin, $xml);
            } else {
                Server::sendDisplayManialinkPage($playerLogin, $xml, $timeoutInSeconds * 1000);
            }
        }
    }

    /**
     * Hide a manialink with the given id for everyone.
     *
     * @param string $id
     */
    public static function hideAll(string $id)
    {
        Server::sendDisplayManialinkPage('', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<manialink version="3" name="EvoSC:' . $id . '" id="' . $id . '"></manialink>');
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
<manialink version="3" name="EvoSC:' . $id . '" id="' . $id . '"></manialink>');
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
                Log::warningWithCause("Failed to render template for $login", $e);
            }
        });

        try{
            Server::executeMulticall();
        }catch(\Exception $e){
            //resend all manialinks individually (slower)
            self::$multiCalls->each(function ($xml, $login) {
                try {
                    Server::sendDisplayManialinkPage($login, $xml, 0, false);
                } catch (Exception $e) {
                    Log::warningWithCause("Failed to render template for $login", $e);
                }
            });
        }

        self::$multiCalls = collect();
    }

    /**
     * Render the template and get the xml as string.
     *
     * @param string $index
     * @param array|null $values
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
