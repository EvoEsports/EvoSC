<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Interfaces\ControllerInterface;
use Illuminate\Support\Collection;
use stdClass;

class ConfigController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static Collection $config;

    /** @var string */
    protected static string $configFilePattern = '/\.config\.json$/';

    /**
     * @var Collection
     */
    private static Collection $rawConfigs;

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
     * @param bool $getRaw
     * @return mixed|null
     */
    public static function getConfig(string $id, bool $getRaw = false)
    {
        if ($getRaw) {
            if (self::$rawConfigs->has($id)) {
                return self::$rawConfigs->get($id);
            }
        }

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

    /**
     * @param string $id
     * @param string|stdClass|array|int|float|double|bool $value
     */
    public static function saveConfig(string $id, $value)
    {
        self::setConfig($id, $value);

        $idParts = collect(explode('.', $id));
        $file = $idParts->shift();

        $configFile = configDir($file . '.config.json');
        $jsonData = File::get($configFile, true);
        $path = $idParts->map(function ($part) {
            return sprintf("{'%s'}", $part);
        })->implode('->');

        eval('$jsonData->' . $path . ' = $value;/** @noinspection PhpUndefinedVariableInspection */');
        File::put($configFile, json_encode($jsonData, JSON_PRETTY_PRINT));

        Log::write("Updated config $id", isVerbose());
    }

    /**
     * @param string $id
     * @param string|stdClass|array|int|float|double|bool $value
     */
    public static function setConfig(string $id, $value)
    {
        self::$config->put($id, $value);
    }

    public static function loadConfigurationFiles()
    {
        $defaultConfigFiles = [
            ...File::getFilesRecursively(configDir('default'), self::$configFilePattern),
            ...File::getFilesRecursively(coreDir('Modules'), self::$configFilePattern),
            ...File::getFilesRecursively(modulesDir(), self::$configFilePattern)
        ];
        
        foreach ($defaultConfigFiles as $configFile) {
            $name = basename($configFile);

            if (!File::exists(configDir($name))) {
                File::copy($configFile, configDir($name));  // on fresh installs throws many warnings on Windows (mkdir), no big issues though
            } else {
                $sourceJson = File::get($configFile, true);
                $targetJson = File::get(configDir($name), true);
                $targetJson = self::copyAttributesRecursively($sourceJson, $targetJson);
                File::put(configDir($name), json_encode($targetJson, JSON_PRETTY_PRINT));
            }
        }

        $configFiles = File::getFiles(coreDir('../config'), self::$configFilePattern)
            ->mapWithKeys(function ($configFile) {
                $configFile = realpath($configFile);
                $name = basename($configFile);
                $name = preg_replace(self::$configFilePattern, '', $name);
                $data = File::get($configFile, true);

                return [$name => $data];
            });

        self::$rawConfigs = $configFiles;
        self::createConfigCache();
    }

    private static function copyAttributesRecursively($sourceJson, $targetJson)
    {
        if ($sourceJson != $targetJson) {
            foreach ($sourceJson as $key => $value) {
                if ($value instanceof stdClass) {
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

    private static function createConfigCache()
    {
        $map = collect();

        self::$rawConfigs->each(function ($value, $base) use ($map) {
            self::createPathsRecursively($base, $value)->each(function ($value, $path) use ($map) {
                if ($value === null) {
                    $value = false;
                }

                $map->put($path, $value);
            });
        });

        if (isVeryVerbose()) {
            $map->each(function ($value, $key) {
                if ($value instanceof stdClass) {
                    $data = 'stdClass';
                } else {
                    if (is_array($value)) {
                        $data = 'array';
                    } elseif (is_bool($value)) {
                        $data = $value ? 'true' : 'false';
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
        $base = strtolower($base);
        $paths = collect();

        foreach ($values as $key => $value) {
            if ($value instanceof stdClass) {
                $paths = $paths->merge(self::createPathsRecursively($base . '.' . strtolower($key), $value));
            } else {
                $paths->put($base . '.' . strtolower($key), $value);
            }

            $paths->put($base, $value);
        }

        return $paths;
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        // TODO: Implement start() method.
    }
}