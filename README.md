# EvoSC

[![Status](https://img.shields.io/badge/STATUS-Unstable-red.svg?style=for-the-badge&link=http://google.com/)](https://github.com/EvoTM/EvoSC/)
[![GitHub](https://img.shields.io/github/license/EvoTM/EvoSC.svg?style=for-the-badge)](https://github.com/EvoTM/EvoSC/blob/master/LICENSE.md)
[![Discord](https://img.shields.io/discord/384138149686935562.svg?color=7289DA&label=DISCORD&style=for-the-badge&logo=discord)](https://discord.gg/4PKKesS)
[![Patreon](https://img.shields.io/endpoint.svg?url=https%3A%2F%2Fshieldsio-patreon.herokuapp.com%2Fevotm&style=for-the-badge)](https://www.patreon.com/evotm)

A server controller for Trackmania² based on PHP 7.2 with Maniaplanet 4.1 support.

| ⚠ WARNING: The controller is not ready to run _stable_ on a live server in its current state. |
| --- |

### Requirements
* PHP 7.2+
* MySql or MariaDB Server

### Installation (From GitHub)
###### Requirements
* Composer
###### Clean installation
1. Clone project `git clone https://github.com/EvoTM/EvoSC.git`.
2. Switch to the new directory.
3. Install required packages with `composer install`.
4. Run EvoSC with `php esc run`.

| ⚠ If the cache and log folder are not created automatically, you need to create them and restart the controller. |
| --- |
###### Updating a github installation
1. Go to the EvoSC directory you want to update and run `git pull`. 

:no_entry: **Do not use the develop-branch unless you are a developer.** The branch can be unstable and we do not have the time and ressources to give support at all times. Safe updates are always pushed to the master-branch.

### Music server installation
Download the [music-server](https://github.com/EvoTM/EvoSC/raw/master/core/Modules/music-client/music-server.zip) and extract it to your webserver with the ogg-files. Copy the `music.config.json` from the music-client-module directory to your config directory and set `url` to the URL of your webserver.

___

## EvoSC CLI

Get all available commands `php esc list`

| Action | Description |
| --------- | -------------------------------------------- |
| Run EvoSC | In terminal type `php esc run (-v/-vv/-vvv/-s/-f)`. -v/vv/vvv for verbosity. -f will skip map verification on start. -s will skip migrations on start.|
| Import data from UASECO | In terminal type `php esc import:uaseco {host} {database} {user} {password}` optionally add `{table_prefix}` |
| Import data from PyPlanet | In terminal type `php esc import:pyplanet {host} {database} {user} {password}` optionally add `{table_prefix}` |
| Fix player scores and ranking | Run `php esc fix:scores` to re-calculate all scores and fix the player ranks. |
| Creating a database migration | Run `php esc make:migration <MigrationClassName>`. The migration is saved to to /Migrations. Copy it to your module if necessary. |

___

## Basic Documentation
* [Collections](https://laravel.com/docs/5.8/collections)
* [Query Builder](https://laravel.com/docs/5.8/queries)
* [Models](https://laravel.com/docs/5.7/eloquent-relationships)
* [Guzzle (REST-Client)](http://docs.guzzlephp.org/en/stable/)

### Modules
Each module must contain a base class in the `esc\Modules` namespace and a `module.json` containing:
```json
{
  "name": "",
  "description": "",
  "author": "",
  "version": 1.0
}
```
Modules can contain Templates, Classes, Models and Database-Migrations. The constructor of the base class is called on controller start, after all controllers have beeb started.
Configs are in json format and are located at the base directory of your module. Name it as your-config-file.config.json, it will automatically be loaded on controller-start.
