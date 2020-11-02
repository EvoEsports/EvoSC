| ⚠ Bug-Reports are only accepted for the master-branch ⚠ |
| --- |
| If you encounter a bug, create an [Issue](https://github.com/EvoTM/EvoSC/issues) describing the problem and maybe the way that led to it. Console logs and screenshots of errors can help, too. |
| If you fail to set up EvoSC on your own, then it is probably too early for you to run EvoSC and you should wait for the official release packages, which will contain all required files. EvoSC is still in development stage and we do not have the resources to help you through the setup process. Version 1.0 is planned to release in 2020. |


# EvoSC

[![Status](https://img.shields.io/badge/STATUS-almost_stable-orange.svg?style=for-the-badge&link=http://google.com/)](https://github.com/EvoTM/EvoSC/)
[![GitHub](https://img.shields.io/github/license/EvoTM/EvoSC.svg?style=for-the-badge)](https://github.com/EvoTM/EvoSC/blob/master/LICENSE.md)
[![Discord](https://img.shields.io/discord/384138149686935562.svg?color=7289DA&label=DISCORD&style=for-the-badge&logo=discord)](https://discord.gg/4PKKesS)
[![Patreon](https://img.shields.io/endpoint.svg?url=https%3A%2F%2Fshieldsio-patreon.herokuapp.com%2Fevotm&style=for-the-badge)](https://www.patreon.com/evotm)

A server controller for Trackmania²

**Supported-Modes:**
* TimeAttack
* Rounds
* ~~Teams~~
* ~~Chase~~





### Requirements
* PHP 7.4 and simplexml, mbstring, gd, dom, mysql extension.
* Composer
* MySql/MariaDB Server

### Installation
[Wiki: Installation](https://github.com/EvoTM/EvoSC/wiki/Installation)

___

## EvoSC CLI

Get all available commands with `php esc list`

| Action | Description |
| --------- | -------------------------------------------- |
| Get EvoSC version | Run `php esc version` to get the installed version. |
| Run EvoSC | Run `php esc run (-v/-vv/-vvv/-s/-f)`. -v/vv/vvv for verbosity. -f will skip map verification on start. -s will skip migrations on start.|
| Import data from UASECO | Run `php esc import:uaseco {host} {database} {user} {password}` optionally add `{table_prefix}` |
| Import data from PyPlanet | Run `php esc import:pyplanet {host} {database} {user} {password}` optionally add `{table_prefix}` |
| Fix player scores and ranking | Run `php esc fix:scores` to re-calculate all scores and fix the player ranks. |
| Creating a database migration | Run `php esc make:migration <MigrationClassName>`. The migration is saved to to /Migrations. Copy it to your module if necessary. |

---

## Ingame Fonts
* RajdhaniMono (default)
* Oswald
* OswaldMono
* GameFontBlack
* GameFontRegular
* GameFontSemiBold
* RobotoCondensed
* RobotoCondensedBold