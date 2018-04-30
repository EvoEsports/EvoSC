<?php

function formatScore(int $score): string
{
    $seconds = floor($score / 1000);
    $ms = $score - ($seconds * 1000);
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;

    return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
}

function formatScoreNoMinutes(int $score): string
{
    $seconds = floor($score / 1000);
    $ms = $score - ($seconds * 1000);

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

function baseDir(string $filename = ''): string
{
    return __DIR__ . '/../' . $filename;
}

function onlinePlayers(): \Illuminate\Support\Collection
{
    $playerlist = \esc\Classes\Server::getPlayerList();
    $logins = collect($playerlist)->pluck(['login']);

    return esc\Models\Player::whereIn('Login', $logins)->get();
}

function finishPlayers(): \Illuminate\Support\Collection
{
    return esc\Models\Player::where('Score', '>', 0)
        ->get();
}

function cutZeroes(string $formattedScore): string
{
    return preg_replace('/^[0\:\.]+/', '', $formattedScore);
}

function secondary(string $str = ""): string
{
    return '$' . config('color.secondary') . $str;
}

function primary(string $str = ""): string
{
    return '$' . config('color.primary') . $str;
}

function warning(string $str = ""): string
{
    return '$' . config('color.warning') . $str;
}

function info(string $str = ""): string
{
    return '$' . config('color.warning') . $str;
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
    $mps = config('server.mps');
    $maps = config('server.maps') . '/';
    if (file_exists($maps . $filename) && file_exists($mps)) {
        $process = new \Symfony\Component\Process\Process($mps . ' /parsegbx=' . $maps . $filename);
        $process->run();

        return json_decode($process->getOutput());
    }
}

function call_func($function, ...$arguments)
{
    $className = explode('::', $function)[0];
    $functionName = explode('::', $function)[1];

    $class = classes()->where('class', $className)->first();

    if ($class) {
        if ($arguments) {
            call_user_func_array("$class->namespace::$functionName", $arguments);
        } else {
            call_user_func("$class->namespace::$functionName");
        }
    } else {
        if ($arguments) {
            call_user_func_array($functionName, $arguments);
        } else {
            call_user_func($functionName);
        }
    }
}

//color functions
function background_color()
{
    return config('color.ui.background');
}

function header_color()
{
    return config('color.ui.header');
}

function primary_color()
{
    return config('color.ui.primary');
}