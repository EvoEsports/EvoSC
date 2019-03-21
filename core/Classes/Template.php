<?php

namespace esc\Classes;


use esc\Controllers\TemplateController;
use esc\Models\Player;
use Illuminate\Support\Collection;

class Template
{
    public $index;
    public $template;

    /**
     * @var Collection
     */
    private static $multicall;

    public function __construct(string $index, string $template)
    {
        $this->index    = $index;
        $this->template = $template;
    }

    public static function showAll(string $index, array $values = null)
    {
        if (!$values) {
            $values = [];
        }

        $xml = TemplateController::getTemplate($index, $values);
        Server::sendDisplayManialinkPage('', $xml);
    }

    public static function hideAll(string $index)
    {
        self::showAll('blank', [
            'id' => $index,
        ]);
    }

    /**
     * @param \esc\Models\Player $player
     * @param string             $index
     * @param null               $values
     * @param bool               $multicall
     */
    public static function show(Player $player, string $index, $values = null, bool $multicall = false)
    {
        $data = [];

        if ($values instanceof Collection) {
            foreach ($values as $key => $value) {
                $data[$key] = $value;
            }
        } else {
            $data = $values;
        }

        $data['localPlayer'] = $player;
        $xml                 = TemplateController::getTemplate($index, $data);

        if ($xml != '') {
            if ($multicall) {
                if (!self::$multicall) {
                    self::$multicall = collect();
                }

                self::$multicall->put($player->Login, $xml);
            } else {
                Server::sendDisplayManialinkPage($player->Login, $xml);
            }
        }
    }

    public static function executeMulticall()
    {
        if (!self::$multicall) {
            return;
        }

        self::$multicall->each(function ($xml, $login) {
            Server::sendDisplayManialinkPage($login, $xml, 0, false, true);
        });

        Server::executeMulticall();

        self::$multicall = collect();
    }

    public static function toString(string $index, array $values = null): string
    {
        if (!$values) {
            $values = [];
        }

        return TemplateController::getTemplate($index, $values);
    }

    public static function hide(Player $player, string $index)
    {
        self::show($player, 'blank', [
            'id' => $index,
        ]);
    }

    public static function getScript(string $templateId)
    {
        $template = TemplateController::getTemplates()->where('id', $templateId)->first();

        if (!$template) {
            //Unknown template
            return null;
        }

        if ($scriptStartPos = strpos($template->template, '<script>')) {
            $script       = substr($template->template, $scriptStartPos);
            $scriptEndPos = strpos($script, '</script>') - 8;

            return substr($script, 8, $scriptEndPos);
        }

        //template has no scripts
        return null;
    }
}