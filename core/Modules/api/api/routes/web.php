<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => 'web'], function () {
    Route::get('/', function () {
        return view('home');
    });

    Route::get('magiclink/{token}', function ($token) {
        if ($token && strlen($token) > 0) {
            $user = \App\User::where('token', $token)->get()->first();
            \Illuminate\Support\Facades\Auth::login($user);
            return redirect('/');
        }

        return redirect('login');
    });

    Route::get('login', function (\Illuminate\Http\Request $request) {
        return view('login');
    })->name('login');

    Route::get('logout', function () {
        \Illuminate\Support\Facades\Auth::logout();
        return redirect('/');
    });

    Route::group(['prefix' => 'groups', 'middleware' => 'auth:web'], function () {
        Route::get('/', 'GroupController@showGroups');
        Route::get('{groupId}/edit', 'GroupController@editGroup');
        Route::post('update', 'GroupController@updateGroup');
    });

    Route::get('js/{script}', function ($script) {
        return file_get_contents(public_path('js/' . $script));
    });
    Route::get('css/{style}', function ($style) {
        return file_get_contents(public_path('css/' . $style));
    });
});

Route::group(['prefix' => 'api'], function () {
    Route::get('online', function () {
        $onlinePlayers = app('db')->table('players')->whereOnline(true)->get();
        $onlinePlayers->each(function (&$player) {
            $player->NickName = preg_replace('/(?<![$])\${1}(?:l(?:\[.+?\])|[iwngosz]{1}|[\w\d]{1,3})/i', '', $player->NickName);
            $player->Group = app('db')->table('groups')->whereId($player->Group)->get()->first() ?? '';
        });
        return json_encode($onlinePlayers);
    });
});