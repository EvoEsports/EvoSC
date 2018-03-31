<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function showGroups()
    {
        $groups = \App\Group::all();
        return view('group.list', compact('groups'));
    }

    public function editGroup($groupId)
    {
        $group = \App\Group::whereId($groupId)->get()->first();
        $rights = app('db')->table('access-rights')->get();
        $access = app('db')->table('access_right_group')->join('access-rights', 'access-rights.id', '=', 'access_right_group.access_right_id')->where('group_id', $group->id)->get();
        return view('group.edit', compact('group', 'rights', 'access'));
    }

    public function updateGroup(Request $request)
    {
        $group = \App\Group::find($request->groupId);

        if (!$group) {
            return back()->with('error', 'Invalid group');
        }

        $group->accessRights()->detach();

        foreach ($request->data as $accessRightId) {
            $group->accessRights()->attach($accessRightId);
        }

        return back();
    }
}
