<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\AccessRight;
use esc\Models\Group;
use esc\Models\Player;

class GroupManager
{
    public function __construct()
    {
        ChatCommand::add('//groups', [self::class, 'showOverview'], 'Show groups manager', 'group');

        ManiaLinkEvent::add('group.overview', [self::class, 'showOverview'], 'group');
        ManiaLinkEvent::add('group.create', [self::class, 'groupCreate'], 'group');
        ManiaLinkEvent::add('group.delete', [self::class, 'groupDelete'], 'group');
        ManiaLinkEvent::add('group.edit_access', [self::class, 'groupEditAccess'], 'group');
        ManiaLinkEvent::add('group.edit_group', [self::class, 'groupEdit'], 'group');
        ManiaLinkEvent::add('group.allow', [self::class, 'groupAllow'], 'group');
        ManiaLinkEvent::add('group.deny', [self::class, 'groupDeny'], 'group');
        ManiaLinkEvent::add('group.update', [self::class, 'groupUpdate'], 'group');
        ManiaLinkEvent::add('group.members', [self::class, 'groupMembers'], 'group');
        ManiaLinkEvent::add('group.member_remove', [self::class, 'groupMemberRemove'], 'group');
        ManiaLinkEvent::add('group.member_add_form', [self::class, 'groupMemberAddForm'], 'group');
        ManiaLinkEvent::add('group.member_add', [self::class, 'groupMemberAdd'], 'group');

        AccessRight::createIfMissing('group_edit', 'Add/delete/update groups.');
        AccessRight::createIfMissing('group_change', 'Change player group.');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Group Manager', 'group.overview', 'group');
        }
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
            warningMessage('Group name ', secondary($input), ' already taken.')->send($player);

            return;
        }

        $group = Group::create(['Name' => $input]);

        if ($group) {
            infoMessage('Created new group: ', secondary($input))->send($player);
            self::showOverview($player);
        } else {
            warningMessage('Failed to create group: ', secondary($input))->send($player);
        }
    }

    public static function groupUpdate(Player $player, string $groupId, string $prefix, string $color)
    {
        $group = Group::find($groupId);

        if ($group) {
            $group->update([
                'chat_prefix' => $prefix,
                'color'       => $color,
            ]);

            self::showOverview($player);
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

    public static function groupEditAccess(Player $player, string $groupId)
    {
        $group        = Group::find($groupId);
        $accessRights = AccessRight::all();

        Template::show($player, 'group-manager.edit_access', compact('group', 'accessRights'));
    }

    public static function groupEdit(Player $player, string $groupId)
    {
        $group = Group::find($groupId);

        Template::show($player, 'group-manager.edit', compact('group'));
    }

    public static function groupAllow(Player $player, string $groupId, string $rightId)
    {
        $group = Group::find($groupId);
        $right = AccessRight::find($rightId);

        if ($group && $right) {
            $group->accessRights()->attach($right->id);
        }
    }

    public static function groupDeny(Player $player, string $groupId, string $rightId)
    {
        $group = Group::find($groupId);
        $right = AccessRight::find($rightId);

        if ($group && $right) {
            $group->accessRights()->detach($right->id);
        }
    }

    public static function groupMembers(Player $player, string $groupId)
    {
        $group = Group::find($groupId);

        if ($group) {
            $playerCount = $group->player()->count();
            Template::show($player, 'group-manager.members', compact('group', 'playerCount'));
        }
    }

    public static function groupMemberRemove(Player $player, string $groupId, string $memberLogin)
    {
        $member = Player::find($memberLogin);

        if ($member) {
            Player::whereLogin($memberLogin)->update(['Group' => 3]);
            infoMessage($player, ' removed ', $member, '\'s access rights.')->sendAll();
            self::groupMembers($player, $groupId);
        }
    }

    public static function groupMemberAddForm(Player $player, string $groupId)
    {
        $players     = onlinePlayers();
        $playerCount = $players->count();

        Template::show($player, 'group-manager.add', compact('players', 'groupId', 'playerCount'));
    }

    public static function groupMemberAdd(Player $player, string $groupId, string $playerLogin)
    {
        $newMember = Player::find($playerLogin);

        if (!$newMember) {
            $newMember = new Player();

            $newMember->NickName = $playerLogin;
            $newMember->Login = $playerLogin;

            $newMember->save();
        }

        $group = Group::find($groupId);

        if ($newMember) {
            Player::whereLogin($playerLogin)->update(['Group' => $group->id]);
            Hook::fire('GroupChanged', $newMember);

            if ($newMember->group->id == 3) {
                infoMessage($player->group, ' ', $player, ' added ', $newMember, ' to group ', secondary($group))->sendAll();
            } else {
                infoMessage($player->group, ' ', $player, ' changed ', $newMember, '\'s group to ', secondary($group))->sendAll();
            }
        }
    }
}