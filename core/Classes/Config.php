<?php

namespace esc\Classes;


use esc\Models\Player;
use Illuminate\Support\Collection;

class Config
{
    public $id;
    public $data;
    public $file;

    private static $configs;

    public static function get(string $pathString)
    {
        $path = collect(explode('.', $pathString));
        $configId = $path->shift();
        $config = self::$configs->get($configId);

        if (!$config) {
            Log::error("Unknown config file: $configId");

            return null;
        }

        $out = $config->data;

        foreach ($path as $variable) {
            if (isset($out->{$variable})) {
                $out = $out->{$variable};
            } else {
                return null;
            }
        }

        return $out;
    }

    /**
     * Change config value and fire ConfigUpdated
     *
     * @param string $pathString
     * @param        $value
     *
     * @return null
     */
    public static function set(string $pathString, $value)
    {
        $path = collect(explode('.', $pathString));
        $configId = $path->shift();
        $config = self::$configs->get($configId);

        if (!$config) {
            Log::error("Unknown config file: $configId");

            return null;
        }

        $config->data = self::updateData($config->data, $path, $value);
        $configFile = configDir(basename($config->file));

        if (!File::exists(configDir($configId . '.config.json'))) {
            //No custom settings file
            copy($config->file, $configFile);
        }

        File::put($configFile, json_encode($config->data, JSON_PRETTY_PRINT));

        Hook::fire('ConfigUpdated', $config);
    }

    public static function setChatCmd(Player $player, string $cmd, string $pathString, string $value)
    {
        self::set($pathString, $value);
    }

    /**
     * Belongs to set
     *
     * @param                                $data
     * @param \Illuminate\Support\Collection $path
     * @param                                $value
     *
     * @return mixed
     */
    private static function updateData($data, Collection $path, $value)
    {
        $newPath = $path;
        $node = $newPath->shift();

        if ($path->count() == 1) {
            $data->{$node}->{$path->implode('')} = $value;
        } else {
            $data->{$node} = self::updateData($data->{$node}, $newPath, $value);
        }

        return $data;
    }

    /**
     * Loads all configuration files in config dir
     */
    public static function loadConfigFiles(...$args)
    {
        self::$configs = collect();

        //Load default configs from module folders
        $moduleConfigFiles = self::getConfigFiles(coreDir('Modules' . DIRECTORY_SEPARATOR));
        $moduleConfigFiles->each([self::class, 'loadConfigFile']);

        //Load custom configs
        $configFolderFiles = self::getConfigFiles(configDir());
        $configFolderFiles->each([self::class, 'loadConfigFile']);
    }

    public static function loadConfigFile(string $filename)
    {
        if (preg_match('/\/default\//', $filename)) {
            //Do not load shipped configs
            return;
        }

        $data = file_get_contents($filename);

        $config = new Config();
        $config->data = json_decode($data);
        $config->id = preg_replace('/\.config\.json/i', '', basename($filename));
        $config->file = $filename;

        self::$configs->put($config->id, $config);
    }

    private static function getConfigFiles($path): Collection
    {
        $collection = collect();

        collect(scandir($path))->reject(function ($file) {
            //Ignore dot files
            return preg_match('/^\./', $file);
        })->each(function ($file) use ($path, &$collection) {
            $fullFilePath = $path . $file;

            if (is_dir($fullFilePath)) {
                //Recursively scan directories
                $newPath = $fullFilePath . DIRECTORY_SEPARATOR;
                $collection = $collection->merge(self::getConfigFiles($newPath));
            } else {
                if (preg_match('/\.config\.json/i', $file)) {
                    //File is config
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