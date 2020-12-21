<?php


namespace EvoSC\Modules\SocialMedia;


use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ConfigController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class SocialMedia extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::migrateOldModules();

        Hook::add('PlayerConnect', [self::class, 'showWidget']);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showWidget(Player $player)
    {
        $links = collect(config('social-media.links'))->map(function ($link) {
            if (preg_match('/^\$(.+)$/', $link->color, $matches)) {
                $link->color = config($matches[1]);
            }

            return $link;
        })->values();

        Template::show($player, 'SocialMedia.widget', compact('links'));
    }

    /**
     * Copies configs of old button widgets and disables them. The modules will be removed in future releases.
     */
    private static function migrateOldModules()
    {
        if (File::exists(configDir('discord.config.json'))) {
            $data = json_decode(File::get(configDir('discord.config.json')));

            if (!empty($data->url) && $data->enabled) {
                $entry = [
                    'title' => 'Discord',
                    'url' => $data->url,
                    'icon' => 'https://i.imgur.com/RxQLC4y.png',
                    'color' => '7289da',
                    'size' => '1x1',
                    'bg_image' => ''
                ];

                $existing = config('social-media.links');
                array_push($existing, $entry);
                ConfigController::saveConfig('social-media.links', $existing);
                ConfigController::saveConfig('discord.enabled', false);
            }
        }
        if (File::exists(configDir('website.config.json'))) {
            $data = json_decode(File::get(configDir('website.config.json')));

            if (!empty($data->url) && $data->enabled) {
                $entry = [
                    'title' => 'Website',
                    'url' => $data->url,
                    'icon' => 'https://i.imgur.com/PY8VOiI.png',
                    'color' => config('theme.hud.accent'),
                    'size' => '1x1',
                    'bg_image' => ''
                ];

                $existing = config('social-media.links');
                array_push($existing, $entry);
                ConfigController::saveConfig('social-media.links', $existing);
                ConfigController::saveConfig('website.enabled', false);
            }
        }
        if (File::exists(configDir('paypal.config.json'))) {
            $data = json_decode(File::get(configDir('paypal.config.json')));

            if (!empty($data->url) && $data->enabled) {
                $entry = [
                    'title' => 'PayPal',
                    'url' => $data->url,
                    'icon' => 'https://i.imgur.com/c0zQ2of.png',
                    'color' => '1f264f',
                    'size' => '1x1',
                    'bg_image' => ''
                ];

                $existing = config('social-media.links');
                array_push($existing, $entry);
                ConfigController::saveConfig('social-media.links', $existing);
                ConfigController::saveConfig('paypal.enabled', false);
            }
        }
        if (File::exists(configDir('patreon.config.json'))) {
            $data = json_decode(File::get(configDir('patreon.config.json')));

            if (!empty($data->url) && $data->enabled) {
                $entry = [
                    'title' => 'Patreon',
                    'url' => $data->url,
                    'icon' => 'https://i.imgur.com/0TXj7E9.png',
                    'color' => 'ff424d',
                    'size' => '1x1',
                    'bg_image' => ''
                ];

                $existing = config('social-media.links');
                array_push($existing, $entry);
                ConfigController::saveConfig('social-media.links', $existing);
                ConfigController::saveConfig('patreon.enabled', false);
            }
        }
    }
}