<?php

use Carbon\Carbon;
use EvoSC\Classes\ChatMessage;
use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Classes\Server;
use EvoSC\Controllers\ConfigController;
use EvoSC\Controllers\PlayerController;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
 * @param mixed ...$message
 * @return ChatMessage
 */
function successMessage(...$message)
{
    return (new ChatMessage(...$message))->setIsSuccess();
}

/**
 * @param mixed ...$message
 * @return ChatMessage
 */
function dangerMessage(...$message)
{
    return (new ChatMessage(...$message))->setIsDanger();
}

/**
 * @param int $score
 *
 * @param bool $cutZero
 * @return string
 */
function formatScore(int $score, bool $cutZero = false): string
{
    $sign = '';

    if ($score < 0) {
        $sign = '-';
    }

    $score = abs($score);
    $seconds = floor($score / 1000);
    $ms = $score - ($seconds * 1000);
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;

    if ($cutZero) {
        return $sign . preg_replace('/^0:0?/', '', sprintf('%d:%02d.%03d', $minutes, $seconds, $ms));
    }

    return $sign . sprintf('%d:%02d.%03d', $minutes, $seconds, $ms);
}

/**
 * @param string|null $styled
 * @param bool $keepLinks
 *
 * @return string
 */
function stripAll(?string $styled = '', bool $keepLinks = false): string
{
    if ($keepLinks) {
        return preg_replace('/(?<![$])\${1}(?:[iwngosz]{1}|[\w\d]{1,3})/i', '', $styled);
    }

    return preg_replace('/(?<![$])\${1}(([lh])(?:\[.+?])|[iwngosz]{1}|[\w\d]{1,3})/i', '', $styled);
}

/**
 * @param string $id
 * @param null $default
 *
 * @return null
 */
function config(string $id, $default = null)
{
    $data = ConfigController::getConfig(strtolower($id));

    if (!$data && is_bool($data)) {
        return false;
    }

    return $data ?: $default;
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
 * @return string
 */
function mapsDir(string $filename = ''): string
{
    return Server::getMapsDirectory() . str_replace('/', DIRECTORY_SEPARATOR, $filename);
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
function modulesDir(string $filename = ''): string
{
    return __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../modules/' . $filename);
}

/**
 * @param string $filename
 *
 * @return string
 */
function ghost(string $filename = ''): string
{
    return Server::GameDataDirectory() . str_replace('/', DIRECTORY_SEPARATOR, '/Replays/Ghosts/' . $filename . '.Replay.Gbx');
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
 * @return Collection
 * @todo implement $withSpectators
 */
function onlinePlayers(): Collection
{
    $logins = array_column(Server::getPlayerList(), 'login');

    return Player::whereIn('Login', $logins)->get();
}

function accessPlayers(string $accessRight): Collection
{
    $logins = array_column(Server::getPlayerList(), 'login');

    return Player::whereIn('Login', $logins)->get()->filter(function (Player $player) use ($accessRight) {
        return $player->hasAccess($accessRight);
    });
}

function ml_escape(string $string)
{
    $out = str_replace('{', '\u007B', str_replace('}', '\u007D', $string));
    return str_replace('"', '\u0022', $out);
}

/**
 * @param string $login
 * @param bool $addToOnlineIfOffline
 *
 * @return Player
 */
function player(string $login, bool $addToOnlineIfOffline = false): Player
{
    if (PlayerController::hasPlayer($login)) {
        return EvoSC\Controllers\PlayerController::getPlayer($login);
    }

    $player = Player::find($login);

    if (!$player || !isset($player->Login)) {
        Log::write('Failed to find player: ' . $login);
        $data = collect(Server::getPlayerList())->where('login', $login)->first();

        if ($data) {
            $player = Player::create([
                'Login' => $data->login,
                'NickName' => $data->nickName,
            ]);
        } else {
            $player = Player::create([
                'Login' => $login,
                'NickName' => $login,
            ]);
        }
    }

    if ($addToOnlineIfOffline) {
        PlayerController::putPlayer($player);
    }

    return $player;
}

/**
 * @return Collection
 */
function echoPlayers(): Collection
{
    return onlinePlayers()->filter(function (Player $player) {
        return $player->hasAccess('admin_echoes');
    });
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
 * get secondary color
 *
 * @param string $str
 *
 * @return string
 */
function secondary(string $str = ""): string
{
    return '$z$s$' . config('theme.chat.highlight') . $str;
}

/**
 * @return string
 */
function getEscVersion(): string
{
    return '0.87.11';
}

/**
 * @param mixed ...$objects
 */
function dd(...$objects)
{
    var_dump(...$objects);
    exit(0);
}

/**
 * @param mixed ...$objects
 */
function dump(...$objects)
{
    var_dump(...$objects);
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

    return $_isDebug ?? false;
}

/**
 * @return bool
 */
function isWindows(): bool
{
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

/**
 * Translation function
 *
 * @param string $id
 * @param array $vars
 * @param string $language
 * @return Object|string|string[]|null
 */
function __(string $id, array $vars = [], string $language = 'en')
{
    $parts = explode('.', $id);
    $base = array_shift($parts);
    $file = coreDir('Dictionary/' . $language . '/' . $base . '.json');

    if (!File::exists($file)) {
        $file = coreDir('Dictionary/en/' . $base . '.json');

        if (!File::exists($file)) {
            return $id;
        }
    }

    $data = File::get($file, true);

    $root = $data;
    while (count($parts) > 0) {
        $root = $root->{array_shift($parts)};
    }

    if (!$root) {
        return $id;
    }

    foreach ($vars as $key => $var) {
        $root = str_replace(':' . $key, $var, $root);
    }

    return $root;
}

/**
 * @param $title
 * @return string
 */
function evo_str_slug($title)
{
    return Str::slug($title, '-', 'en');
}

/**
 *
 */
function restart_evosc()
{
    warningMessage(secondary('EvoSC v' . getEscVersion()), ' is restarting.')->sendAll();
    Server::chatEnableManualRouting(false);
    Log::warning('Old process is terminating.');
    pcntl_exec(PHP_BINARY, $_SERVER['argv']);
    warningMessage('$f00[CRITICAL]', ' Failed to restart EvoSC. Please restart it manually.')->sendAdmin();
    Log::error('[CRITICAL] Failed to create new process, dying...');
    exit(56);
}