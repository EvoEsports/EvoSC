<?php

namespace EvoSC\Models;


use EvoSC\Classes\Cache;
use EvoSC\Controllers\MapController;
use EvoSC\Modules\Dedimania\Models\Dedi;
use EvoSC\Modules\MxKarma\Models\Karma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use stdClass;

/**
 * Class Map
 *
 * @package EvoSC\Models
 *
 * @property string $id
 * @property string $uid
 * @property string $filename
 * @property string $folder
 * @property Player $author
 * @property boolean $enabled
 * @property string $last_played
 * @property string $mx_id
 * @property int $cooldown
 * @property int $plays
 * @property string $name
 * @property string $environment
 * @property string $title_id
 *
 */
class Map extends Model
{
    const TABLE = 'maps';

    /**
     * @var string
     */
    protected $table = self::TABLE;

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $dates = ['last_played'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return HasMany
     */
    public function dedis()
    {
        return $this->hasMany(Dedi::class, 'Map');
    }

    /**
     * @return HasOne
     */
    public function author()
    {
        return $this->hasOne(Player::class, 'id', 'author');
    }

    /**
     * @param $playerId
     *
     * @return mixed
     */
    public function getAuthorAttribute($playerId)
    {
        return Player::whereId($playerId)->first();
    }

    /**
     * @return HasMany
     */
    public function ratings()
    {
        return $this->hasMany(Karma::class, 'Map', 'id');
    }

    /**
     * @return mixed|stdClass|null
     */
    public function getMxDetailsAttribute()
    {
        if (!$this->mx_id) {
            return null;
        }

        if (Cache::has('mx-details/' . $this->mx_id)) {
            return Cache::get('mx-details/' . $this->mx_id);
        }

        return null;
    }

    /**
     * @return mixed|stdClass|null
     */
    public function getMxWorldRecordAttribute()
    {
        if (!$this->mx_id) {
            return null;
        }

        if (Cache::has('mx-wr/' . $this->mx_id)) {
            return Cache::get('mx-wr/' . $this->mx_id);
        }

        return null;
    }

    /**
     * @return stdClass
     */
    public function getGbxAttribute()
    {
        $cacheId = 'gbx/' . $this->uid;

        if (Cache::has($cacheId)) {
            $cacheObject = Cache::get($cacheId);

            if (isset($cacheObject->data) && $cacheObject->data != null) {
                return $cacheObject->data;
            }
        }

        $gbx = MapController::getGbxInformation($this->filename, false);
        Cache::put($cacheId, $gbx);

        return $gbx;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * @param string $mapUid
     *
     * @return Map|null
     */
    public static function getByUid(string $mapUid): ?Map
    {
        return Map::whereUid($mapUid)->first();
    }
}