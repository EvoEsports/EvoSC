<?php

namespace EvoSC\Models;


use EvoSC\Classes\DB;
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
    public static function add(string $name, string $description)
    {
        if (!DB::table('access-rights')->where('name', '=', $name)->exists()) {
            AccessRight::create([
                'name' => $name,
                'description' => $description,
            ]);
        }
    }
}