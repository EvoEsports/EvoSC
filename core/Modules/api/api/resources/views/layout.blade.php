<html>
<head>
    <title>ESC Control panel</title>
    <link rel="stylesheet" href="{{mix('css/app.css')}}">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="{{asset('js/app.js')}}"></script>
</head>
<body>
<div class="uk-container uk-margin-top">
    <h1 class="uk-heading-divider">ESC Control Panel</h1>

    <div class="uk-grid">
        <div class="uk-width-expand">
            <ul class="uk-subnav" uk-margin>
                <li><a href="{{url('/')}}">Dashboard</a></li>
                <li><a href="{{url('groups')}}">Groups</a></li>
                <li><a href="{{url('/')}}">Players</a></li>
                <li><a href="{{url('/')}}">Maps</a></li>
                <li><a href="{{url('/')}}">Music</a></li>
            </ul>
        </div>
        <div>
            <ul class="uk-subnav" uk-margin>
                @if(\Auth::check())
                    <li><a href="{{url('logout')}}">({{\Illuminate\Support\Facades\Auth::user()->login}}) Logout</a></li>
                @else
                    <li><a href="{{url('login')}}">Login</a></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="uk-margin-top">
        @yield('content')
    </div>
</div>
</body>
</html>