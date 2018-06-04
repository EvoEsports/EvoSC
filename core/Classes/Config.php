<?php

namespace esc\Classes;


use Illuminate\Support\Collection;

class Config
{
    private static $configs = [];

    public static function get($variable)
    {
        $variableExplode = explode('.', $variable);
        $config          = array_shift($variableExplode);

        if (!array_key_exists($config, self::$configs)) {
            Log::error("Trying to access unloaded config: $variable");
            return null;
        }

        $out = self::$configs[$config];

        foreach ($variableExplode as $variable) {
            if (isset($out->{$variable})) {
                $out = $out->{$variable};
            } else {
                return null;
            }
        }

        return $out;
    }

    /**
     * Loads all configuration files in config dir
     */
    public static function loadConfigFiles(...$args)
    {
        $moduleConfigFiles = self::getConfigFiles(coreDir('Modules' . DIRECTORY_SEPARATOR));
        $moduleConfigFiles->each([self::class, 'loadConfigFile']);

        $configFolderFiles = self::getConfigFiles(configDir());
        $configFolderFiles->each([self::class, 'loadConfigFile']);
    }

    public static function loadConfigFile(string $filename)
    {
        if (preg_match('/\/default\//', $filename)) {
            return;
        }

        $data   = file_get_contents($filename);
        $config = json_decode($data);
        $id     = preg_replace('/\.config\.json/i', '', basename($filename));

        $configs = Config::getConfigs();

        if (array_key_exists($id, $configs)) {
            foreach ($config as $key => $attribute) {
                $configs[$id]->{$key} = $config->{$key};
            }
        } else {
            $configs[$id] = $config;
        }

        Config::setConfigs($configs);
    }

    private static function getConfigFiles($path): Collection
    {
        $collection = collect();

        collect(scandir($path))->reject(function ($file) {
            return preg_match('/^\./', $file);
        })->each(function ($file) use ($path, &$collection) {
            $fullFilePath = $path . $file;

            if (is_dir($fullFilePath)) {
                $newPath    = $fullFilePath . DIRECTORY_SEPARATOR;
                $collection = $collection->merge(self::getConfigFiles($newPath));
            } else {
                if (preg_match('/\.config\.json/i', $file)) {
                    $collection->push($fullFilePath);
                }
            }
        });

        return $collection;
    }

    public static function configReload()
    {
        self::loadConfigFiles();
    }

    /**
     * @return array
     */
    public static function getConfigs(): array
    {
        return self::$configs;
    }

    /**
     * @param array $configs
     */
    public static function setConfigs(array $configs): void
    {
        self::$configs = $configs;
    }
}