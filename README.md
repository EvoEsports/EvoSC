![version:0.62.0](https://img.shields.io/badge/version-0.62.0-blue.svg?style=flat-square)
![status:unreleased](https://img.shields.io/badge/Status-not_ready_for_production-red.svg?style=flat-square)

# EvoSC

A server controller for Trackmania² based on PHP 7.2 with Maniaplanet 4.1 support.

### Requirements
* PHP 7.2+
* MySql or MariaDB Server

### Installation (git)
###### Requirements
* Composer
###### Installation
1. Clone project `git clone https://github.com/EvoTM/EvoSC.git`
2. Go into the directory and run `composer install`
3. Copy contents from `config/default` to `config` and fill out the required fields
4. Run `php esc migrate` to create the database tables.
5. Run EvoSC

___

# EvoSC CLI

Get all available commands `php esc list`

| Action | Description |
| --------- | -------------------------------------------- |
| Run EvoSC | In terminal type `php esc run (-v\|-vv\|-vvv)` |
| Import data from UASECO | In terminal type `php esc import:uaseco {host} {database} {user} {password}` optionally add `{table_prefix}` |
| Fix player scores and ranking | Run `php esc fix:scores` to re-calculate all scores and fix the player ranks. |
| Creating a database migration | Run `php esc make:migration <MigrationClassName>`. The migration is saved to to /Migrations. Copy it to your module if necessary. |

___

# Basic Documentation
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