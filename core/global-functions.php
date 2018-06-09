<?php

function formatScore(int $score): string
{
    $seconds = floor($score / 1000);
    $ms      = $score - ($seconds * 1000);
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;

    return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
}

function serverName(): string
{
    global $serverName;
    return $serverName;
}

function console(): \esc\Models\Player
{
    $player           = new \esc\Models\Player();
    $player->Login    = serverName();
    $player->NickName = serverName();
    $player->Group    = \esc\Models\Group::find(1);
    return $player;
}

function formatScoreNoMinutes(int $score): string
{
    $seconds = floor($score / 1000);
    $ms      = $score - ($seconds * 1000);

    return sprintf('%d.%03d', $seconds, $ms);
}

function stripColors(?string $colored): string
{
    return preg_replace('/(?<![$])\${1}(?:[\w\d]{1,3})/i', '', $colored);
}

function stripStyle(?string $styled = '', bool $keepLinks = false): string
{
    if ($keepLinks) {
        return preg_replace('/(?<![$])\${1}(?:[iwngosz]{1})/i', '', $styled);
    }

    return preg_replace('/(?<![$])\${1}(?:l(?:\[.+?\])|[iwngosz]{1})/i', '', $styled);
}

function stripAll(?string $styled = '', bool $keepLinks = false): string
{
    if ($keepLinks) {
        return preg_replace('/(?<![$])\${1}(?:[iwngosz]{1}|[\w\d]{1,3})/i', '', $styled);
    }

    return preg_replace('/(?<![$])\${1}(?:l(?:\[.+?\])|[iwngosz]{1}|[\w\d]{1,3})/i', '', $styled);
}

function config(string $id, $default = null)
{
    return esc\Classes\Config::get($id) ?: $default;
}

function cacheDir(string $filename = ''): string
{
    return __DIR__ . '/../cache/' . $filename;
}

function ghost(string $filename = ''): string
{
    $basePath = str_replace('/', '/', config('server.base'));

    return $basePath . 'UserData/Replays/Ghosts/' . $filename . '.Replay.Gbx';
}

function musicDir(string $filename = ''): string
{
    return __DIR__ . '/../music/' . $filename;
}

function coreDir(string $filename = ''): string
{
    return __DIR__ . '/' . $filename;
}

function configDir(string $filename = ''): string
{
    return __DIR__ . '/../config/' . $filename;
}

function baseDir(string $filename = ''): string
{
    return __DIR__ . '/../' . $filename;
}

function onlinePlayers(): \Illuminate\Support\Collection
{
    return \esc\Models\Player::where('player_id', '>', 0)->get();
}

function finishPlayers(): \Illuminate\Support\Collection
{
    return esc\Models\Player::where('Score', '>', 0)->get();
}

function cutZeroes(string $formattedScore): string
{
    return preg_replace('/^[0\:\.]+/', '', $formattedScore);
}

function secondary(string $str = ""): string
{
    return '$' . config('colors.secondary') . $str;
}

function primary(string $str = ""): string
{
    return '$' . config('colors.primary') . $str;
}

function warning(string $str = ""): string
{
    return '$' . config('colors.warning') . $str;
}

function info(string $str = ""): string
{
    return '$' . config('colors.warning') . $str;
}

function getEscVersion(): string
{
    global $escVersion;

    return $escVersion;
}

function maps()
{
    return \esc\Models\Map::whereEnabled(true)
                          ->get();
}

function matchSettings(string $filename = null)
{
    return config('server.base') . '/UserData/Maps/MatchSettings/' . ($filename);
}

function getMapInfoFromFile(string $filename)
{
    $mps  = config('server.mps');
    $maps = config('server.maps') . '/';
    if (file_exists($maps . $filename) && file_exists($mps)) {
        $process = new \Symfony\Component\Process\Process($mps . ' /parsegbx=' . $maps . $filename);
        $process->run();

        return json_decode($process->getOutput());
    }
}

//color functions
function background_color()
{
    return config('colors.ui.background');
}

function header_color()
{
    return config('colors.ui.header');
}

function primary_color()
{
    return config('colors.ui.primary');
}

function createCrashReport($e)
{
    if (!($e instanceof \Exception)) {
        return;
    }

    $crashReport = collect();
    $crashReport->put('file', $e->getFile());
    $crashReport->put('line', $e->getLine());
    $crashReport->put('message', $e->getMessage() . "\n" . $e->getTraceAsString());

    if (!is_dir(__DIR__ . '/../crash-reports')) {
        mkdir(__DIR__ . '/../crash-reports');
    }

    $filename = sprintf(__DIR__ . '/../crash-reports/%s.json', date('Y-m-d_Hi', time()));
    file_put_contents($filename, $crashReport->toJson());
}

function isVerbose(): bool
{
    return \esc\Classes\Log::getOutput()->isVerbose();
}

function isVeryVerbose(): bool
{
    return \esc\Classes\Log::getOutput()->isVeryVerbose();
}

function isDebug(): bool
{
    return \esc\Classes\Log::getOutput()->isDebug();
}