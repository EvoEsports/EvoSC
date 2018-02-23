<?php

namespace esc\classes;


use esc\controllers\ChatController;
use esc\controllers\PlayerController;
use esc\models\Group;
use esc\models\Player;

class Module
{
    public $name;
    public $class;

    public function __construct(string $name, string $class)
    {
        $this->name = $name;
        $this->class = $class;
    }

    public function load()
    {
        new $this->class();
    }
}