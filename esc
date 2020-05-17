<?php

//require 'vendor/composer/autoload_psr4.php';
require 'vendor/autoload.php';
require 'core/global-functions.php';

use EvoSC\Commands\GetVersion;
use EvoSC\Commands\EscRun;
use EvoSC\Commands\AddAdmin;
use EvoSC\Commands\FakeLocals;
use EvoSC\Commands\FixRanks;
use EvoSC\Commands\FixScores;
use EvoSC\Commands\ImportUaseco;
use EvoSC\Commands\ImportPyplanet;
use EvoSC\Commands\MakeMigration;
use EvoSC\Commands\ChatRouter;
use EvoSC\Commands\Migrate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

global $_isVerbose;
global $_isVeryVerbose;
global $_isDebug;
global $_skipMapCheck;
$_isVerbose = false;
$_isVeryVerbose = false;
$_isDebug = false;
$_skipMapCheck = false;

$input = new ArgvInput();
if ($input->hasParameterOption('-vvv', true) ||
    $input->hasParameterOption('--verbose=3', true) ||
    3 === $input->getParameterOption('--verbose', false, true)) {
    $_isDebug = true;
    $_isVerbose = true;
    $_isVeryVerbose = true;
} elseif ($input->hasParameterOption('-vv', true) ||
    $input->hasParameterOption('--verbose=2', true) ||
    2 === $input->getParameterOption('--verbose', false, true)) {
    $_isVeryVerbose = true;
    $_isVerbose = true;
} elseif ($input->hasParameterOption('-v', true) ||
    $input->hasParameterOption('--verbose=1', true) ||
    $input->hasParameterOption('--verbose', true) ||
    $input->getParameterOption('--verbose', false, true)) {
    $_isVerbose = true;
}

$application = new Application();
$application->add(new Migrate());
$application->add(new MakeMigration());
$application->add(new ImportUaseco());
$application->add(new ImportPyplanet());
$application->add(new FixRanks());
$application->add(new FixScores());
$application->add(new GetVersion());
$application->add(new EscRun());
$application->add(new FakeLocals());
$application->add(new ChatRouter());
$application->add(new AddAdmin());
$application->setDefaultCommand("list");

try {
    $application->run();
} catch (Exception $e) {
    die($e);
}
