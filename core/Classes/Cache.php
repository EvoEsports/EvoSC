<?php


namespace esc\Classes;


use Carbon\Carbon;

class Cache
{
    /**
     * Checks if a cache object exists and is not expired
     *
     * @param  string  $id
     *
     * @return bool
     */
    public static function has(string $id): bool
    {
        if (!File::exists(cacheDir($id))) {
            return false;
        }

        try {
            $cacheObject = File::get(cacheDir($id), true);

            if (!$cacheObject->expires) {
                return true;
            }

            return (new Carbon($cacheObject->expires))->isFuture();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gets an cache object (may be outdated, do check with "has" first!)
     *
     * @param  string  $id
     *
     * @return mixed
     */
    public static function get(string $id)
    {
        $cacheObject = File::get(cacheDir($id), true);

        return $cacheObject->data;
    }

    /**
     * @param  string  $id
     *
     * @return Carbon|null
     */
    public static function getAdded(string $id): ?Carbon
    {
        $cacheObject = File::get(cacheDir($id), true);

        try {
            return (new Carbon($cacheObject->expires));
        } catch (\Exception $e) {
        }

        return null;
    }

    /**
     * Saves an object to the cache directory
     *
     * @param                     $id
     * @param                     $data
     * @param  \Carbon\Carbon|null  $expires
     */
    public static function put($id, $data, Carbon $expires = null)
    {
        try {
            $cacheObject = new \stdClass();
            $cacheObject->data = $data;
            $cacheObject->added = now();
            $cacheObject->expires = $expires;

            File::put(cacheDir($id), $cacheObject, true);
        } catch (\Exception $e) {
            Log::write("Failed to save $id.");
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
        }
    }

    public static function forget(string $string)
    {
        File::delete(cacheDir($string));
    }
}