<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\MatchSettings;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Controllers\TemplateController;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MatchSettingsManager
{
    private static $path;
    private static $objects;

    private static $modes = [
        'TimeAttack' => 'timeattack.xml',
        'Team'       => 'team.xml',
        'Rounds'     => 'rounds.xml',
        'Laps'       => 'laps.xml',
        'Cup'        => 'cup.xml',
        'Chase'      => 'chase.xml',
    ];

    public function __construct()
    {
        self::$path    = Server::getMapsDirectory() . '/MatchSettings/';
        self::$objects = collect();

        AccessRight::createIfNonExistent('ms_edit', 'Change match-settings.');

        ChatCommand::add('//msm', [self::class, 'showMatchSettingsOverview'], 'Show MatchSettingsManager', 'ms_edit');

        ManiaLinkEvent::add('msmn.overview', [self::class, 'showOverview'], 'ms_edit');
        ManiaLinkEvent::add('msmn.create', [self::class, 'showCreateMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msmn.edit', [self::class, 'showEditMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msmn.load', [self::class, 'loadMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msmn.duplicate', [self::class, 'duplicateMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msmn.delete', [self::class, 'deleteMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msmn.new', [self::class, 'createNewMatchsettings'], 'ms_edit');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'MatchSetting Manager', 'msm.overview', 'map.edit');
        }
    }

    public static function showOverview(Player $player)
    {
        $matchsettings = self::getMatchsettings();

        Template::show($player, 'matchsettings-manager.overview', compact('matchsettings'));
    }

    public static function showCreateMatchsettings(Player $player)
    {
        $modes = collect(self::$modes)->keys();

        Template::show($player, 'matchsettings-manager.create', compact('modes'));
    }

    public static function showEditMatchsettings(Player $player, string $name)
    {
        $file = Server::getMapsDirectory() . '/MatchSettings/' . $name . '.txt';
        $data = File::get($file);

        Template::show($player, 'matchsettings-manager.edit', compact('data', 'name'));
    }

    public static function createNewMatchsettings(Player $player, string $modeName)
    {
        $modeFile               = self::$modes[$modeName];
        $modeBaseName           = str_replace('.xml', '', $modeFile);
        $sourceMatchsettings    = __DIR__ . '/MatchSettingsRepo/' . $modeFile;
        $matchsettingsDirectory = Server::getMapsDirectory() . '/MatchSettings/';
        $i                      = 0;

        do {
            $filename = sprintf('%s_%d.txt', $modeBaseName, $i);
            $i++;
        } while (File::exists($matchsettingsDirectory . $filename));

        File::copy($sourceMatchsettings, $matchsettingsDirectory . $filename);

        self::showOverview($player);
    }

    public static function duplicateMatchsettings(Player $player, string $matchsettingsFile)
    {
        $file                   = $matchsettingsFile . '.txt';
        $targetFile             = $matchsettingsFile . '_copy.txt';
        $matchsettingsDirectory = Server::getMapsDirectory() . '/MatchSettings/';

        File::copy($matchsettingsDirectory . $file, $matchsettingsDirectory . $targetFile);

        self::showOverview($player);
    }

    public static function deleteMatchsettings(Player $player, string $matchsettingsFile)
    {
        File::delete(Server::getMapsDirectory() . '/MatchSettings/' . $matchsettingsFile . '.txt');

        self::showOverview($player);
    }

    public static function loadMatchsettings(Player $player, string $matchsettingsFile)
    {
        Server::loadMatchSettings('MatchSettings/' . $matchsettingsFile . '.txt');

        infoMessage($player, ' loads matchsettings ', secondary($matchsettingsFile))->sendAll();
    }

    public static function getMatchsettings()
    {
        $files = File::getDirectoryContents(self::$path, '/\.txt$/')->map(function (String $file) {
            return preg_replace('/\.txt$/', '', $file);
        });

        return $files;
    }
}