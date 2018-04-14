![version:0.24.0](https://img.shields.io/badge/Version-0.24.0-blue.svg)

# EvoSC

A server controller for Trackmania² based on PHP 7.2 and Maniaplanet 4 support.

### Requirements
* PHP 7.2
* Composer installed

### Installation
1. Clone project
2. Run `composer install`
3. Copy contents from `config/default` to `config` and fill out the required fields

### Run ESC
1. In terminal type `php esc.php`

### Import data from UASECO
1. Fill out information in `config/uaseco.json`
2. Run `php import_uaseco.php`

# Module Quickguide
1. Create directory in `modules` 
2. Create `module.json` in the created directory containing a name and the main php class 
````json
{
  "name": "My module name",
  "main": "MyModule"
}
````
3. Create `MyModule.php` containing your main class
Example:
````php
<?php

class MyModule
{
    /**
     * Constructor, will be called automatically on start
     */
    public function __construct()
    {
        //Include models or other php files
        include_once __DIR__ . '/Model/MyModel.php';
        
        //Register a hook
        //Args: string $event, string $function
        \esc\Classes\Hook::add('HookName', 'MyModule::callbackFunc'); //core classes need namespace, module classes not
        
        //Register a chat command
        //Args: string $command, string $callback, string $description = '-', string $trigger = '/', string $access = null
        \esc\Controllers\ChatController::addCommand('command', 'CC:callbackFunc', 'description', '/'); //if you want to restrict access to admin, you can use 'ban' access-right for now
        
        //Create a timer
        //string $id, string $callback, string $delayTime, bool $override = false
        //10m = 10minutes, 1w2h = 1week+2hours, 5s = 5seconds, 5mo = 5 months
        \esc\Classes\Timer::create('players.count.save', 'CC:cF', '10m');
        
        //Add a template
        \esc\Classes\Template::add('cpr.record', \esc\Classes\File::get(__DIR__ . '/Templates/my-template.latte.xml'));
        //please name your files .latte.xml, latte template engine is used, it is a convention to name the files that way
    }
    
    public static function callbackFunc()
    {
        //do stuff
        //make sure it's public static
    }
}
````