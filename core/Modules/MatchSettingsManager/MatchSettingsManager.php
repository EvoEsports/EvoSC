<?php

namespace EvoSC\Modules\MatchSettingsManager;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MatchSettingsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\QuickButtons\QuickButtons;
use SimpleXMLElement;

class MatchSettingsManager extends Module implements ModuleInterface
{
    private static string $path;
    private static $objects;

    private static array $modes = [
        'TimeAttack' => 'timeattack.xml',
        'Team' => 'team.xml',
        'Rounds' => 'rounds.xml',
        'Laps' => 'laps.xml',
        'Cup' => 'cup.xml',
        'Chase' => 'chase.xml',
    ];

    public function __construct()
    {
        self::$path = Server::getMapsDirectory().'/MatchSettings/';
        self::$objects = collect();

        AccessRight::add('matchsettings_load', 'Load matchsettings.');
        AccessRight::add('matchsettings_edit', 'Edit matchsettings.');

        ChatCommand::add('//msm', [self::class, 'showOverview'], 'Show MatchSettingsManager', 'matchsettings_edit');

        ManiaLinkEvent::add('msm.overview', [self::class, 'showOverview'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.create', [self::class, 'showCreateMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.edit', [self::class, 'showEditMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.edit_maps', [self::class, 'showEditMatchsettingsMaps'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.load', [self::class, 'loadMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.duplicate', [self::class, 'duplicateMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.delete', [self::class, 'deleteMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.new', [self::class, 'createNewMatchsettings'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.update', [self::class, 'updateMatchsettings'], 'matchsettings_edit');

        ManiaLinkEvent::add('msm.save_maps', [self::class, 'saveMaps'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.add_map', [self::class, 'addMap'], 'matchsettings_edit');
        ManiaLinkEvent::add('msm.remove_map', [self::class, 'removeMap'], 'matchsettings_edit');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'MatchSetting Manager', 'msm.overview', 'map.edit');
        }
    }

    public static function showOverview(Player $player)
    {
        $matchsettings = self::getMatchsettings()->values();

        Template::show($player, 'MatchSettingsManager.overview', compact('matchsettings'));
    }

    public static function showCreateMatchsettings(Player $player)
    {
        $modes = collect(self::$modes)->keys();

        Template::show($player, 'MatchSettingsManager.create', compact('modes'));
    }

    public static function addMap(Player $player, string $matchSettingsName, string $mapId)
    {
        $map = Map::find($mapId);

        if ($map) {
            MatchSettingsController::addMap("$matchSettingsName.txt", $map->filename, $map->uid);
            Log::info($player.' added "'.$map.'" to "'.$matchSettingsName.'"');
        }
    }

    public static function removeMap(Player $player, string $matchSettingsName, string $mapId)
    {
        $map = Map::find($mapId);

        if ($map) {
            MatchSettingsController::removeByUid("$matchSettingsName.txt", $map->uid);
            Log::info($player.' removed "'.$map.'" from "'.$matchSettingsName.'"');
        }
    }

    public static function showEditMatchsettings(Player $player, string $name)
    {
        $file = Server::getMapsDirectory().'MatchSettings/'.$name.'.txt';
        $data = File::get($file);
        $nodes = collect();
        $xml = new SimpleXMLElement($data);

        foreach ($xml as $node) {
            if ($node->getName() != 'map') {
                if (count($node) > 0) {
                    $nodeName = $node->getName();
                    $items = collect();

                    foreach ($node as $item) {
                        if ($nodeName == 'mode_script_settings' || $nodeName == 'script_settings') {
                            $items->put(''.$item['name'], ''.$item['value']);
                        } else {
                            $items->put($item->getName(), ''.$item[0]);
                        }
                    }

                    $nodes->put($nodeName, $items);
                } else {
                    $nodes->put($node->getName(), ''.$node[0]);
                }
            }
        }

        @Template::show($player, 'MatchSettingsManager.edit-settings', compact('name', 'nodes'));
    }

    public static function showEditMatchsettingsMaps(Player $player, string $name)
    {
        $perPage = 19;
        $file = Server::getMapsDirectory().'MatchSettings/'.$name.'.txt';
        $data = File::get($file);
        $enabledMapUids = collect();
        $xml = new SimpleXMLElement($data);

        foreach ($xml as $node) {
            if ($node->getName() == 'map') {
                $enabledMapUids->push($node->ident);
            }
        }

        $mapChunks = Map::all()
            ->map(function (Map $map) use ($enabledMapUids) {
                return [
                    'id' => $map->id,
                    'enabled' => $enabledMapUids->contains($map->uid),
                    'environment' => $map->environment,
                    'title_id' => $map->title_id ?? "?",
                    'name' => $map->name,
                    'author_name' => $map->author->NickName,
                    'author_login' => $map->author->Login
                ];
            })
            ->sortByDesc('enabled')
            ->chunk(250);

        for ($i = 0; $i < $mapChunks->count(); $i++) {
            Template::show($player, 'MatchSettingsManager.send-maps',
                ['maps' => $mapChunks->get($i)->values(), 'chunks' => $mapChunks->count(), 'i' => $i]);
        }

        $totalMaps = Map::count();
        $totalPages = ceil($totalMaps / $perPage);

        Template::show($player, 'MatchSettingsManager.edit-maps', compact('name', 'totalPages', 'totalMaps'));
    }

    public static function updateMatchsettings(Player $player, string $oldFilename, string $filename, ...$settings)
    {
        $settings = json_decode(implode(',', $settings));
        $filename = trim($filename);

        foreach ($settings as $setting => $value) {
            MatchSettingsController::updateSetting($oldFilename.'.txt', $setting, $value);
            Log::info($player.' set "'.$setting.'" to "'.$value.'" in "'.$oldFilename.'"');
        }

        if ($oldFilename != $filename) {
            MatchSettingsController::rename($oldFilename.'.txt', $filename.'.txt');
            Log::info($player.' renamed "'.$oldFilename.'" to "'.$filename.'"');
        }

        self::showOverview($player);
    }

    public static function createNewMatchsettings(Player $player, string $modeName)
    {
        $modeFile = self::$modes[$modeName];
        $modeBaseName = str_replace('.xml', '', $modeFile);
        $sourceMatchsettings = __DIR__.'/MatchSettingsRepo/'.$modeFile;
        $matchsettingsDirectory = Server::getMapsDirectory().'MatchSettings/';
        $i = 0;

        do {
            $filename = sprintf('%s_%d.txt', $modeBaseName, $i);
            $i++;
        } while (File::exists($matchsettingsDirectory.$filename));

        File::copy($sourceMatchsettings, $matchsettingsDirectory.$filename);
        Log::info($player.' created new "'.$filename.'" with mode "'.$modeName.'"');

        self::showOverview($player);
    }

    public static function duplicateMatchsettings(Player $player, string $matchsettingsFile)
    {
        $file = $matchsettingsFile.'.txt';
        $targetFile = $matchsettingsFile.'_copy.txt';
        $matchsettingsDirectory = Server::getMapsDirectory().'MatchSettings/';

        File::copy($matchsettingsDirectory.$file, $matchsettingsDirectory.$targetFile);
        Log::info($player.' duplicated "'.$file.'" as "'.$targetFile.'"');

        self::showOverview($player);
    }

    public static function deleteMatchsettings(Player $player, string $matchsettingsFile)
    {
        if (config('server.default-matchsettings') == $matchsettingsFile.'.txt') {
            warningMessage('Can not delete default match-settings.')->send($player);

            return;
        }

        File::delete(Server::getMapsDirectory().'MatchSettings/'.$matchsettingsFile.'.txt');
        Log::warning($player.' deleted "'.$matchsettingsFile.'"');

        self::showOverview($player);
    }

    public static function loadMatchsettings(Player $player, string $matchsettingsFile)
    {
        MatchSettingsController::loadMatchSettings(true, $player, $matchsettingsFile.'.txt');
    }

    public static function getMatchsettings()
    {
        return File::getDirectoryContents(self::$path, '/\.txt$/')->transform(function (String $file) {
            return preg_replace('/\.txt$/', '', $file);
        });
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
    }
}