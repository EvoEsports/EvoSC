<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return view('home');
});

$router->group(['prefix' => 'groups'], function () use ($router) {
    $router->get('/', function () use ($router) {
        $groups = app('db')->table('groups')->get();
        return view('group.list', compact('groups'));
    });

    $router->get('{groupId}/edit', function ($groupId) use ($router) {
        $group = app('db')->table('groups')->whereId($groupId)->get()->first();
        $rights = app('db')->table('access-rights')->get();
        $access = app('db')->table('access_right_group')->join('access-rights', 'access-rights.id', '=', 'access_right_group.access_right_id')->where('group_id', $group->id)->get();
        return view('group.edit', compact('group', 'rights', 'access'));
    });
});

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->get('online', function () {
        $onlinePlayers = app('db')->table('players')->whereOnline(true)->get();
        return json_encode($onlinePlayers);
    });
});