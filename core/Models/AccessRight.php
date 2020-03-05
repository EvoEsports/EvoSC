<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

class AccessRight extends Model
{
    protected $table = 'access-rights';

    protected $fillable = ['name', 'description'];

    public $timestamps = false;

    /**
     * @return string
     */
    public function __toString()
    {
        return strval($this->name);
    }

    /**
     * @param string $name
     * @param string $description
     */
    public static function createIfMissing(string $name, string $description)
    {
        if (AccessRight::whereName($name)->get()->isEmpty()) {
            AccessRight::create([
                'name'        => $name,
                'description' => $description,
            ]);
        }
    }
}