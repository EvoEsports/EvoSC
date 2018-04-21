<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\classes\Database;
use esc\Classes\Log;
use esc\Classes\Template;
use esc\Models\Group;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;

class GroupController
{
    public static function init()
    {
        self::createTables();

        ChatCommand::add('group', 'esc\Controllers\GroupController::group', 'Group commands', '//', 'group');
        ChatCommand::add('groups', 'esc\Controllers\GroupController::displayGroups', 'Show groups overview', '//', 'group');
    }

    public static function createTables()
    {
        $seed = [
            ['id' => 1, 'Name' => 'Masteradmin', 'Protected' => true],
            ['id' => 2, 'Name' => 'Admin', 'Protected' => true],
            ['id' => 3, 'Name' => 'Player', 'Protected' => true],
        ];

        Database::create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Name')->unique();
            $table->boolean('Protected')->default(false);
        }, $seed);
    }

    public static function group(Player $player, $cmd, $action, ...$arguments)
    {
        switch ($action) {
            case 'add':
                self::groupAdd($player, $arguments);
                break;

            default:
                ChatController::message($player, '_warning', 'Invalid action supplied. Valid actions are: add');
        }
    }

    public static function groupAdd(Player $player, ...$arguments)
    {
        if (count($arguments[0]) != 1) {
            ChatController::message($player, '_warning', 'Invalid amount of arguments supplied. Required: name');
            return;
        }

        $groupName = $arguments[0][0];
        $groupNameExists = Group::whereName($groupName)->get()->isNotEmpty();

        if ($groupNameExists) {
            ChatController::message($player, '_warning', 'Group with name ', $groupName, ' already exists');
            return;
        }

        try {
            $group = Group::create([
                'Name' => $groupName
            ]);
        } catch (\Exception $e) {
            Log::logAddLine('GroupController', 'Failed to create group with name ' . $groupName);
            Log::logAddLine($e->getMessage(), $e->getTraceAsString());
            return;
        }

        ChatController::messageAll('_info', $player->group, ' ', $player, ' created group ', $group);

        return;
    }

    public static function displayGroups(Player $player)
    {
        $groups = Group::all();

        $groupList = Template::toString('groups', compact('groups'));

        Template::show($player, 'esc.modal', [
            'id' => 'Groups',
            'width' => 180,
            'height' => 97,
            'content' => $groupList
        ]);
    }
}