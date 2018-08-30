<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\AccessRight;
use esc\Models\Group;
use esc\Models\Player;

class GroupManager
{
    public function __construct()
    {
        ChatCommand::add('groups', [self::class, 'showOverview'], 'Show groups manager', '//', 'group');

        ManiaLinkEvent::add('group.overview', [self::class, 'showOverview'], 'group');
        ManiaLinkEvent::add('group.create', [self::class, 'groupCreate'], 'group');
        ManiaLinkEvent::add('group.delete', [self::class, 'groupDelete'], 'group');
        ManiaLinkEvent::add('group.edit', [self::class, 'groupEdit'], 'group');
        ManiaLinkEvent::add('group.allow', [self::class, 'groupAllow'], 'group');
        ManiaLinkEvent::add('group.deny', [self::class, 'groupDeny'], 'group');

        KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();

        self::groupEdit($player, "" . Group::first()->id);
    }

    public static function showOverview(Player $player)
    {

        $groups = Group::all();

        Template::show($player, 'group-manager.overview', compact('groups'));
    }

    public static function groupCreate(Player $player, $input)
    {
        $groupNameExists = Group::whereName($input)->get()->isNotEmpty();

        if ($groupNameExists) {
            ChatController::message($player, '_warning', 'Group name ', secondary($input), ' already taken.');
            return;
        }

        $group = Group::create(['Name' => $input]);

        if ($group) {
            ChatController::message($player, '_info', 'Created new group: ', secondary($input));
            self::showOverview($player);
        } else {
            ChatController::message($player, '_warning', 'Failed to create group: ', secondary($input));
        }
    }

    public static function groupDelete(Player $player, string $groupId)
    {
        $group = Group::find($groupId);

        if ($group) {
            //Move players from group into default-group
            if ($group->player->isNotEmpty()) {
                $group->player()->update(['Group' => 3]);
            }

            //Delete group
            $group->delete();

            self::showOverview($player);
        }
    }

    public static function groupEdit(Player $player, string $groupId)
    {
        $group = Group::find($groupId);
        $accessRights = AccessRight::all();

        Template::show($player, 'group-manager.edit', compact('group', 'accessRights'));
    }

    public static function groupAllow(Player $player, string $groupId, string $rightId)
    {
        $group = Group::find($groupId);
        $right = AccessRight::find($rightId);

        if($group && $right){
            $group->accessRights()->attach($right->id);
        }
    }

    public static function groupDeny(Player $player, string $groupId, string $rightId)
    {
        $group = Group::find($groupId);
        $right = AccessRight::find($rightId);

        if($group && $right){
            $group->accessRights()->detach($right->id);
        }
    }
}