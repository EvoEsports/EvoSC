<?php


namespace esc\Controllers;


use esc\Classes\File;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use Illuminate\Support\Collection;

class ConfigController implements ControllerInterface
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $config;

    protected static $configFilePattern = '/\.config\.json$/';

    /**
     * Method called on controller boot.
     */
    public static function init()
    {
        self::loadConfigurationFiles();
    }

    /**
     * Get a config variable from cache
     *
     * @param string $id
     *
     * @return mixed|null
     */
    public static function getConfig(string $id)
    {
        if (self::$config->has($id)) {
            return self::$config->get($id);
        }

        return null;
    }

    /**
     * Check if a config value is loaded
     *
     * @param string $id
     *
     * @return bool
     */
    public static function hasConfig(string $id): bool
    {
        return self::$config->has($id);
    }

    private static function loadConfigurationFiles()
    {
        $defaultConfigFiles = collect();
        $defaultConfigFiles = $defaultConfigFiles->merge(File::getFilesRecursively(coreDir('Modules'), self::$configFilePattern));
        $defaultConfigFiles = $defaultConfigFiles->merge(File::getFilesRecursively(coreDir('../config/default'), self::$configFilePattern));
        $defaultConfigFiles = $defaultConfigFiles->merge(File::getFilesRecursively(coreDir('../modules'), self::$configFilePattern));

        $defaultConfigFiles->each(function ($configFile) {
            $name = basename($configFile);

            if (!File::exists(configDir($name))) {
                File::copy($configFile, configDir($name));
            } else {
                $sourceJson = File::get($configFile, true);
                $targetJson = File::get(configDir($name), true);
                $targetJson = self::copyAttributesRecursively($sourceJson, $targetJson);
                File::put(configDir($name), json_encode($targetJson, JSON_PRETTY_PRINT));
            }
        });

        $configFiles = File::getFiles(coreDir('../config'), self::$configFilePattern)->mapWithKeys(function ($configFile) {
            $name = basename($configFile);
            $name = preg_replace(self::$configFilePattern, '', $name);
            $data = File::get($configFile, true);

            return [$name => $data];
        });

        self::createConfigCache($configFiles);
    }

    private static function copyAttributesRecursively($sourceJson, $targetJson)
    {
        if ($sourceJson != $targetJson) {
            foreach ($sourceJson as $key => $value) {
                if ($value instanceof \stdClass) {
                    if (!isset($targetJson->{$key})) {
                        $targetJson->{$key} = $value;
                    } else {
                        $targetJson->{$key} = self::copyAttributesRecursively($sourceJson->{$key}, $targetJson->{$key});
                    }
                } else {
                    if (!isset($targetJson->{$key})) {
                        $targetJson->{$key} = $value;
                    }
                }
            }

            foreach ($targetJson as $key => $value) {
                if (!isset($sourceJson->{$key})) {
                    unset($targetJson->{$key});
                }
            }
        }

        return $targetJson;
    }

    private static function createConfigCache(Collection $config)
    {
        $map = collect();

        $config->each(function ($value, $base) use ($map) {
            self::createPathsRecursively($base, $value)->each(function ($value, $path) use ($map) {
                $map->put($path, $value);
            });
        });

        if (isVeryVerbose()) {
            $map->each(function ($value, $key) {
                if ($value instanceof \stdClass) {
                    $data = 'stdClass';
                } else {
                    if (is_array($value)) {
                        $data = 'array';
                    } else {
                        $data = $value;
                    }
                }

                printf("%30s -> %s\n", $key, $data);
            });
        }

        self::$config = $map;
    }

    private static function createPathsRecursively(string $base, $values)
    {
        $base  = strtolower($base);
        $paths = collect();

        foreach ($values as $key => $value) {
            if ($value instanceof \stdClass) {
                $paths = $paths->merge(self::createPathsRecursively($base . '.' . strtolower($key), $value));
            } else {
                $paths->put($base . '.' . strtolower($key), $value);
            }

            $paths->put($base, $value);
        }

        return $paths;
    }
}