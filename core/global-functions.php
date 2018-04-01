<?php

function formatScore(int $score): string
{
    $seconds = floor($score / 1000);
    $ms = $score - ($seconds * 1000);
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;

    return sprintf('%d.%02d.%03d', $minutes, $seconds, $ms);
}

function formatScoreNoMinutes(int $score): string
{
    $seconds = floor($score / 1000);
    $ms = $score - ($seconds * 1000);

    return sprintf('%d.%03d', $seconds, $ms);
}

function stripColors(?string $colored): string
{
    return preg_replace('/(?<![$])\${1}(?:[\w\d]{3})/i', '', $colored);
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

function onlinePlayers(): \Illuminate\Support\Collection
{
    return esc\Models\Player::whereOnline(true)->get();
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
    return \esc\Models\Map::all();
}

function getMapInfoFromFile(string $fileName)
{
    $mps = config('server.mps');
    $maps = config('server.maps') . '/';
    if (file_exists($maps . $fileName) && file_exists($mps)) {
        $process = new \Symfony\Component\Process\Process($mps . ' /parsegbx=' . $maps . $fileName);
        $process->run();
        return json_decode($process->getOutput());
    }
}