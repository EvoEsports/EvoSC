<?php


namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Controllers\ConfigController;
use esc\Controllers\ModuleController;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\Player;
use Illuminate\Support\Collection;

class ConfigEditor extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::createIfMissing('config_edit', 'Change the server config.');

        ChatCommand::add('//config', [self::class, 'cmdShowEditConfig'], 'Open the Config-Editor', 'config_edit');

        ManiaLinkEvent::add('config_editor_general', [self::class, 'mleShowEditGeneralConfig'], 'config_edit');
        ManiaLinkEvent::add('config_editor_modules', [self::class, 'mleShowEditModulesConfig'], 'config_edit');
        ManiaLinkEvent::add('config_editor_save', [self::class, 'mleSaveConfig'], 'config_edit');
    }

    /**
     * @param Player $player
     * @param $cmd
     */
    public static function cmdShowEditConfig(Player $player, $cmd = null)
    {
        self::mleShowEditGeneralConfig($player);
    }

    /**
     * @param Player $player
     */
    public static function mleShowEditGeneralConfig(Player $player)
    {
        Template::show($player, 'config-editor.general');
    }

    /**
     * @param Player $player
     */
    public static function mleShowEditModulesConfig(Player $player)
    {
        $modules = ModuleController::getModules()->map(function (Module $module) {
            $module->configs = self::mapConfigRecursively(ConfigController::getConfig($module->getConfigId(), true), collect(), 0);

            if ($module->configs->count() == 0) {
                return null;
            }

            return $module;
        })->filter();

        Template::show($player, 'config-editor.modules', compact('modules'));
    }

    private static function mapConfigRecursively($entries, Collection $configs, int $level, $prevKey = '')
    {
        foreach ($entries as $key => $entry) {
            if (is_array($entry) || is_object($entry)) {
                $configs->push([
                    'key' => $key,
                    'full_key' => strlen($prevKey) ? $prevKey . '.' . $key : $key,
                    'blank' => true,
                    'level' => $level
                ]);
                self::mapConfigRecursively($entry, $configs, $level + 1, $key);
            } else {
                $type = '';
                if (is_bool($entry)) {
                    $type = 'bool';
                } else if (is_float($entry)) {
                    $type = 'float';
                } else if (is_int($entry)) {
                    $type = 'int';
                } else if (is_string($entry)) {
                    $type = 'string';
                }

                $configs->push([
                    'key' => $key,
                    'full_key' => strlen($prevKey) ? $prevKey . '.' . $key : $key,
                    'value' => $entry,
                    'type' => $type,
                    'level' => $level
                ]);
            }
        }

        return $configs;
    }

    public static function mleSaveConfig(Player $player, \stdClass $formValues)
    {
        foreach ((array)json_decode($formValues->data) as $key => $updatedValue) {
            $configId = "$formValues->id.$key";
            ConfigController::saveConfig($configId, $updatedValue);
        }
    }
}