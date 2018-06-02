<?php

namespace esc\Classes;


use esc\Controllers\TemplateController;
use esc\Models\Player;

class Template
{
    public $index;
    public $template;

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
            'id' => $index
        ]);
    }

    /**
     * @param Player $player
     * @param string $index
     * @param array|null $values
     * @throws \Exception
     */
    public static function show(Player $player, string $index, array $values = null)
    {
        if (!$values) {
            $values = [];
        }

        $values['localPlayer'] = $player;

        try {
            $xml = TemplateController::getTemplate($index, $values);
        } catch (\Exception $e) {
            throw new \Exception('Failed to compile template: ' . $index);
        }

        Server::sendDisplayManialinkPage($player->Login, $xml);
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
            'id' => $index
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