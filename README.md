![version:0.40.2](https://img.shields.io/badge/version-0.40.2-blue.svg?style=flat-square)
[![GitHub license](https://img.shields.io/github/license/EvolutionTM/EvoSC.svg?style=flat-square)](https://github.com/EvolutionTM/EvoSC/blob/master/LICENSE.md)
[![GitHub issues](https://img.shields.io/github/issues/EvolutionTM/EvoSC.svg?style=flat-square)](https://github.com/EvolutionTM/EvoSC/issues)




# EvoSC

A server controller for Trackmania² based on PHP 7 and Maniaplanet 4 support.

### Requirements
* PHP 7
* Composer installed

### Installation
1. Clone project
2. Run `composer install`
3. Copy contents from `config/default` to `config` and fill out the required fields

# EvoSC command line

Get all available commands `php esc list`

### Run ESC
In terminal type `php esc run`

### Import data from UASECO
In terminal type `php esc import:uaseco {host} {database} {user} {password}` optionally add `{table_prefix}`
