<?php

function formatScore(int $score): string
{
    $seconds = floor($score / 1000);
    $ms = $score - ($seconds * 1000);
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;

    return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
}

function stripColors(string $colored): string
{
    return preg_replace('/(\$[0-9a-f]{3})/', '', $colored);
}

function stripStyle(string $styled, bool $keepLinks = false): string
{
    if ($keepLinks) {
        return preg_replace('/\$[iwngo]/', '', $styled);
    }

    return preg_replace('/(\$[iwngo]|\$l\[.+\)?)/', '', $styled);
}

function config(string $id, $default = null)
{
    return esc\classes\Config::get($id) ?: $default;
}

function cacheDir(string $filename = ''): string
{
    return __DIR__ . '\\cache\\' . $filename;
}

function onlinePlayers(): \Illuminate\Support\Collection
{
    return esc\models\Player::whereOnline(true)->get();
}

function finishPlayers(): \Illuminate\Support\Collection
{
    return esc\models\Player::where('Score', '>', 0)->get();
}

function cutZeroes(string $formattedScore): string
{
    return preg_replace('/^[0\:\.]+/', '', $formattedScore);
}