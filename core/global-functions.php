<?php

use Carbon\Carbon;
use esc\Classes\ChatMessage;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Controllers\ConfigController;
use esc\Controllers\PlayerController;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

/**
 * @param mixed ...$message
 *
 * @return ChatMessage
 */
function chatMessage(...$message)
{
    return new ChatMessage(...$message);
}

/**
 * @param mixed ...$message
 *
 * @return ChatMessage
 */
function infoMessage(...$message)
{
    return (new ChatMessage(...$message))->setIsInfoMessage();
}

/**
 * @param mixed ...$message
 *
 * @return ChatMessage
 */
function warningMessage(...$message)
{
    return (new ChatMessage(...$message))->setIsWarning();
}

/**
 * @param int $score
 *
 * @return string
 */
function formatScore(int $score): string
{
    $seconds = floor($score / 1000);
    $ms = $score - ($seconds * 1000);
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;

    return sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
}

/**
 * @return string
 */
function serverName(): string
{
    global $serverName;

    return $serverName;
}

/**
 * @param int $score
 *
 * @return string
 */
function formatScoreNoMinutes(int $score): string
{
    $seconds = floor($score / 1000);
    $ms = $score - ($seconds * 1000);

    return sprintf('%d.%03d', $seconds, $ms);
}

/**
 * @param string|null $colored
 *
 * @return string
 */
function stripColors(?string $colored): string
{
    return preg_replace('/\${0}\${1}(?:[a-f0-9]{1,3})/i', '', $colored);
}

/**
 * @param string|null $styled
 * @param bool        $keepLinks
 *
 * @return string
 */
function stripStyle(?string $styled = '', bool $keepLinks = false): string
{
    if ($keepLinks) {
        return preg_replace('/(?<![$])\${1}(?:[iwngosz]{1})/i', '', $styled);
    }

    return preg_replace('/(?<![$])\${1}(?:l(?:\[.+?\])|[iwngosz]{1})/i', '', $styled);
}

/**
 * @param string|null $styled
 * @param bool        $keepLinks
 *
 * @return string
 */
function stripAll(?string $styled = '', bool $keepLinks = false): string
{
    if ($keepLinks) {
        return preg_replace('/(?<![$])\${1}(?:[iwngosz]{1}|[\w\d]{1,3})/i', '', $styled);
    }

    return preg_replace('/(?<![$])\${1}((l|h)(?:\[.+?\])|[iwngosz]{1}|[\w\d]{1,3})/i', '', $styled);
}

/**
 * @param string $id
 * @param null   $default
 *
 * @return null
 */
function config(string $id, $default = null)
{
    return ConfigController::getConfig(strtolower($id)) ?: $default;
}

/**
 * @param string $filename
 *
 * @return string
 */
function cacheDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../cache/' . $filename);
}

/**
 * @param string $filename
 *
 * @return string
 */
function logDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../logs/' . $filename);
}

/**
 * @param string $filename
 *
 * @return string
 */
function ghost(string $filename = ''): string
{
    return Server::GameDataDirectory() . str_replace('/', DIRECTORY_SEPARATOR,
            '/Replays/Ghosts/' . $filename . '.Replay.Gbx');
}

/**
 * @param string $filename
 *
 * @return string
 */
function coreDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/' . $filename);
}

/**
 * @param string $filename
 *
 * @return string
 */
function configDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../config/' . $filename);
}

/**
 * @param string $filename
 *
 * @return string
 */
function baseDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../' . $filename);
}

/**
 * @param bool $withSpectators
 *
 * @return Collection
 * @todo implement $withSpectators
 */
function onlinePlayers(bool $withSpectators = true): Collection
{
    $logins = collect(Server::getPlayerList())->pluck('login');

    return Player::whereIn('Login', $logins)->get();
}

/**
 * @param string $login
 * @param bool   $addToOnlineIfOffline
 *
 * @return Player
 */
function player(string $login, bool $addToOnlineIfOffline = false): Player
{
    if (PlayerController::hasPlayer($login)) {
        return esc\Controllers\PlayerController::getPlayer($login);
    }

    $player = Player::find($login);

    if (!$player || !isset($player->Login)) {
        Log::write('Failed to find player: ' . $login);
        $data = collect(Server::getPlayerList())->where('login', $login)->first();

        if ($data) {
            Player::create([
                'Login'    => $data->login,
                'NickName' => $data->nickName,
            ]);
        } else {
            Player::create([
                'Login'    => $login,
                'NickName' => $login,
            ]);
        }

        $player = Player::find($login);
    }

    if ($addToOnlineIfOffline) {
        PlayerController::addPlayer($player);
    }

    return $player;
}

/**
 * @return Collection
 */
function echoPlayers(): Collection
{
    $players = onlinePlayers()->filter(function (Player $player) {
        return $player->hasAccess('admin_echoes');
    });

    return $players;
}

/**
 * @return Collection
 */
function finishPlayers(): Collection
{
    return Player::where('Score', '>', 0)->get();
}

/**
 * @return Carbon
 * @throws Exception
 */
function now(): Carbon
{
    return (new Carbon())->now();
}

/**
 * @param string $formattedScore
 *
 * @return string
 */
function cutZeroes(string $formattedScore): string
{
    return preg_replace('/^[0\:\.]+/', '', $formattedScore);
}

/**
 * get secondary color
 *
 * @param string $str
 *
 * @return string
 */
function secondary(string $str = ""): string
{
    return '$z$s$' . config('colors.secondary') . $str;
}

/**
 * get primary color
 *
 * @param string $str
 *
 * @return string
 */
function primary(string $str = ""): string
{
    return '$' . config('colors.primary') . $str;
}

/**
 * @param string $str
 *
 * @return string
 */
function warning(string $str = ""): string
{
    return '$' . config('colors.warning') . $str;
}

/**
 * @param string $str
 *
 * @return string
 */
function info(string $str = ""): string
{
    return '$' . config('colors.info') . $str;
}

/**
 * @return string
 */
function getEscVersion(): string
{
    return '0.71.0';
}

/**
 * @return mixed
 */
function maps()
{
    return Map::whereEnabled(true)->get();
}

/**
 * @param string|null $filename
 *
 * @return string
 */
function matchSettings(string $filename = null)
{
    return Server::getMapsDirectory() . '/MatchSettings/' . ($filename);
}

/**
 * @param $object
 */
function dd($object)
{
    var_dump($object);
    die();
}

/**
 * @param string $filename
 *
 * @return mixed|null
 */
function getMapInfoFromFile(string $filename)
{
    $mps = config('server.mps');
    $maps = config('server.maps') . '/';
    if (file_exists($maps . $filename) && file_exists($mps)) {
        $process = new Process([$mps, ' /parsegbx=' . $maps . $filename]);
        $process->run();

        return json_decode($process->getOutput());
    }

    return null;
}

/**
 * @return bool
 */
function isVerbose(): bool
{
    global $_isVerbose;
    global $_isVeryVerbose;
    global $_isDebug;

    return ($_isVerbose || $_isVeryVerbose || $_isDebug);
}

/**
 * @return bool
 */
function isVeryVerbose(): bool
{
    global $_isVeryVerbose;
    global $_isDebug;

    return ($_isVeryVerbose || $_isDebug);
}

/**
 * @return bool
 */
function isDebug(): bool
{
    global $_isDebug;

    return $_isDebug;
}

/**
 * @return bool
 */
function isWindows(): bool
{
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}