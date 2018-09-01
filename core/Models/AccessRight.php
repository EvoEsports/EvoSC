<?php

namespace esc\Models;


use Illuminate\Database\Eloquent\Model;

class AccessRight extends Model
{
    protected $table = 'access-rights';

    protected $fillable = ['name', 'description'];

    public $timestamps = false;

    public function __toString()
    {
        return $this->name;
    }

    public static function createIfNonExistent(string $name, string $description)
    {
        if (self::whereName($name)->get()->isEmpty()) {
            self::create([
                'name'        => $name,
                'description' => $description,
            ]);
        }
    }
}