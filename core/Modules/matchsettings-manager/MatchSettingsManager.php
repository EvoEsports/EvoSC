<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Controllers\MatchSettingsController;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MatchSettingsManager implements ModuleInterface
{
    private static $path;
    private static $objects;

    private static $modes = [
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

        AccessRight::createIfMissing('ms_edit', 'Change match-settings.');

        ChatCommand::add('//msm', [self::class, 'showOverview'], 'Show MatchSettingsManager', 'ms_edit');

        ManiaLinkEvent::add('msm.overview', [self::class, 'showOverview'], 'ms_edit');
        ManiaLinkEvent::add('msm.create', [self::class, 'showCreateMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.edit', [self::class, 'showEditMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.edit_maps', [self::class, 'showEditMatchsettingsMaps'], 'ms_edit');
        ManiaLinkEvent::add('msm.load', [self::class, 'loadMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.duplicate', [self::class, 'duplicateMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.delete', [self::class, 'deleteMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.new', [self::class, 'createNewMatchsettings'], 'ms_edit');
        ManiaLinkEvent::add('msm.update', [self::class, 'updateMatchsettings'], 'ms_edit');

        ManiaLinkEvent::add('msm.save_maps', [self::class, 'saveMaps'], 'ms_edit');
        ManiaLinkEvent::add('msm.add_map', [self::class, 'addMap'], 'ms_edit');
        ManiaLinkEvent::add('msm.remove_map', [self::class, 'removeMap'], 'ms_edit');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'MatchSetting Manager', 'msm.overview', 'map.edit');
        }
    }

    public static function showOverview(Player $player)
    {
        $matchsettings = self::getMatchsettings()->values();

        Template::show($player, 'matchsettings-manager.overview', compact('matchsettings'));
    }

    public static function showCreateMatchsettings(Player $player)
    {
        $modes = collect(self::$modes)->keys();

        Template::show($player, 'matchsettings-manager.create', compact('modes'));
    }

    public static function addMap(Player $player, string $matchSettingsName, string $mapId)
    {
        $map = Map::find($mapId);

        if ($map) {
            MatchSettingsController::addMap("$matchSettingsName.txt", $map);
            Log::write($player.' added "'.$map.'" to "'.$matchSettingsName.'"');
        }
    }

    public static function removeMap(Player $player, string $matchSettingsName, string $mapId)
    {
        $map = Map::find($mapId);

        if ($map) {
            MatchSettingsController::removeByUid("$matchSettingsName.txt", $map->uid);
            Log::write($player.' removed "'.$map.'" from "'.$matchSettingsName.'"');
        }
    }

    public static function showEditMatchsettings(Player $player, string $name)
    {
        $file = Server::getMapsDirectory().'MatchSettings/'.$name.'.txt';
        $data = File::get($file);
        $nodes = collect();
        $xml = new \SimpleXMLElement($data);

        foreach ($xml as $node) {
            if ($node->getName() != 'map') {
                if (count($node) > 0) {
                    $nodeName = $node->getName();
                    $items = collect();

                    foreach ($node as $item) {
                        if ($nodeName == 'mode_script_settings') {
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

        @Template::show($player, 'matchsettings-manager.edit-settings', compact('name', 'nodes'));
    }

    public static function showEditMatchsettingsMaps(Player $player, string $name)
    {
        $perPage = 19;
        $file = Server::getMapsDirectory().'MatchSettings/'.$name.'.txt';
        $data = File::get($file);
        $enabledMapUids = collect();
        $xml = new \SimpleXMLElement($data);

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
                    'title_id' => $map->title_id,
                    'name' => $map->name,
                    'author_name' => $map->author->NickName,
                    'author_login' => $map->author->Login
                ];
            })
            ->sortByDesc('enabled')
            ->chunk(250);

        for ($i = 0; $i < $mapChunks->count(); $i++) {
            Template::show($player, 'matchsettings-manager.send-maps',
                ['maps' => $mapChunks->get($i)->values(), 'chunks' => $mapChunks->count(), 'i' => $i]);
        }

        $totalMaps = Map::count();
        $totalPages = ceil($totalMaps / $perPage);

        Template::show($player, 'matchsettings-manager.edit-maps', compact('name', 'totalPages', 'totalMaps'));
    }

    public static function updateMatchsettings(Player $player, string $oldFilename, string $filename, ...$settings)
    {
        $settings = json_decode(implode(',', $settings));
        $filename = trim($filename);

        foreach ($settings as $setting => $value) {
            MatchSettingsController::updateSetting($oldFilename.'.txt', $setting, $value);
        }

        if ($oldFilename != $filename) {
            MatchSettingsController::rename($oldFilename.'.txt', $filename.'.txt');
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

        self::showOverview($player);
    }

    public static function duplicateMatchsettings(Player $player, string $matchsettingsFile)
    {
        $file = $matchsettingsFile.'.txt';
        $targetFile = $matchsettingsFile.'_copy.txt';
        $matchsettingsDirectory = Server::getMapsDirectory().'MatchSettings/';

        File::copy($matchsettingsDirectory.$file, $matchsettingsDirectory.$targetFile);

        self::showOverview($player);
    }

    public static function deleteMatchsettings(Player $player, string $matchsettingsFile)
    {
        if (config('server.default-matchsettings') == $matchsettingsFile.'.txt') {
            warningMessage('Can not delete default match-settings.')->send($player);

            return;
        }

        File::delete(Server::getMapsDirectory().'MatchSettings/'.$matchsettingsFile.'.txt');

        self::showOverview($player);
    }

    public static function loadMatchsettings(Player $player, string $matchsettingsFile)
    {
        MatchSettingsController::loadMatchSettings($player, $matchsettingsFile.'.txt');
    }

    public static function getMatchsettings()
    {
        $files = File::getDirectoryContents(self::$path, '/\.txt$/')->transform(function (String $file) {
            return preg_replace('/\.txt$/', '', $file);
        });

        return $files;
    }

    public static function sendFilteredMapsPage(Player $player, string $name, int $page, ...$search)
    {
        $perPage = 19;
        $search = trim(implode(',', $search));
        $file = Server::getMapsDirectory().'MatchSettings/'.$name.'.txt';
        $data = File::get($file);
        $enabledMaps = collect();
        $xml = new \SimpleXMLElement($data);

        foreach ($xml as $node) {
            if ($node->getName() == 'map') {
                $enabledMaps->push($node);
            }
        }

        $maps = Map::all();

        if ($search) {
            $maps = $maps->filter(function (Map $map) use ($search) {
                return mb_strpos(stripAll($map->name), $search);
            });
        }

        $maps = $maps->map(function (Map $map) use ($enabledMaps) {
            if (!$map->name) {
                $map->name = $map->gbx->Name;
                $map->environment = $map->gbx->Environment;
                $map->title_id = $map->gbx->TitleId;
                $map->save();
            }

            return [
                'enabled' => $enabledMaps->contains('ident', $map->uid),
                'name' => str_replace('"', '', $map->name),
                'author' => $map->author->NickName,
                'environment' => $map->environment,
                'title_id' => $map->title_id
            ];
        })
            ->sortByDesc('enabled')
            ->chunk($perPage)
            ->get($page)
            ->values();

        Template::show($player, 'matchsettings-manager.send-maps', [
            'maps' => $maps->toJson(),
            'page' => $page
        ]);
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function start(string $mode)
    {
        ManiaLinkEvent::add('msm.load_page', [self::class, 'sendFilteredMapsPage']);
    }
}