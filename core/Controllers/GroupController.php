<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\classes\Database;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Models\AccessRight;
use esc\Models\Group;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;

class GroupController
{
    public static function init()
    {
        ChatCommand::add('group', 'GroupController::group', 'Group commands', '//', 'group');
        ChatCommand::add('groups', 'GroupController::displayGroups', 'Show groups overview', '//', 'group');

        ManiaLinkEvent::add('group.delete', 'GroupController::groupDelete', 'group');
        ManiaLinkEvent::add('group.edit', 'GroupController::groupEdit', 'group');
        ManiaLinkEvent::add('group.toggle.access', 'GroupController::groupToggleAccessRight', 'group');
        ManiaLinkEvent::add('groups.show', 'GroupController::groupsShow', 'group');
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
    }

    public static function groupDelete(Player $player, $id)
    {
        $group = Group::find($id);

        if ($group) {
            ChatController::messageAll('_info', $player->group, ' ', $player, ' deleted group ', $group);
            Group::whereId($id)->delete();
            self::displayGroups($player);
            return;
        } else {
            Log::logAddLine('GroupController', 'Group with id ' . $id . ' not found');
        }
    }

    public static function groupEdit(Player $player, $id)
    {
        $group = Group::find($id);

        if (!$group) {
            ChatController::message($player, '_warning', 'Invalid group selected');
            Log::logAddLine('GroupController', 'Invalid group selected: ' . $id);
            return;
        }

        $accessRights = AccessRight::all();
        $groupEdit = Template::toString('group-edit', compact('accessRights', 'group'));

        Template::hide($player, 'Groups');
        Template::show($player, 'components.modal', [
            'id' => 'Group - Edit',
            'width' => 90,
            'height' => count($accessRights) * 4.6 + 26,
            'content' => $groupEdit,
            'onClose' => 'groups.show'
        ]);
    }

    public static function groupToggleAccessRight(Player $player, $groupId, $accessRightId, bool $hasAccess)
    {
        $group = Group::find($groupId);

        if ($hasAccess) {
            $group->accessRights()->detach($accessRightId);
        } else {
            $group->accessRights()->attach($accessRightId);
        }

        self::groupEdit($player, $groupId);
    }

    public static function groupsShow(Player $player)
    {
        Template::hide($player, 'Group - Edit');
        self::displayGroups($player);
    }

    public static function displayGroups(Player $player)
    {
        $groups = Group::all();

        $groupList = Template::toString('groups', compact('groups'));

        Template::show($player, 'components.modal', [
            'id' => 'Groups',
            'width' => 90,
            'height' => count($groups) * 4.5 + 15,
            'content' => $groupList
        ]);
    }
}