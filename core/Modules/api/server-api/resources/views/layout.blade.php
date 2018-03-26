<html>
<head>
    <title>ESC Control panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-beta.40/css/uikit.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-beta.40/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue"></script>
</head>
<body>
<div class="uk-container uk-margin-top">
    <h1 class="uk-heading-divider">ESC Control Panel</h1>

    <div>
        <ul class="uk-subnav" uk-margin>
            <li><a href="{{url('/')}}">Dashboard</a></li>
            <li><a href="{{url('groups')}}">Groups</a></li>
            <li><a href="{{url('/')}}">Players</a></li>
            <li><a href="{{url('/')}}">Maps</a></li>
            <li><a href="{{url('/')}}">Music</a></li>
        </ul>
    </div>

    <div class="uk-margin-top">
        @yield('content')
    </div>
</div>
</body>
</html>