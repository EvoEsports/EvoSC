@extends('layout')

@section('content')
    <h4>Connected players</h4>
    <table id="players" class="uk-table uk-table-expand uk-table-striped uk-table-small">
        <thead>
        <th>Name</th>
        <th>Group</th>
        <th>Score</th>
        <th>Action</th>
        </thead>
        <tbody>
        <tr v-for="player in players">
            <td>@{{player.NickName}}</td>
            <td>@{{player.Group.Name}}</td>
            <td>@{{player.Score == 0 ? 'not finished' : formatScore(player.Score)}}</td>
            <td><a href="/">Edit</a></td>
        </tr>
        </tbody>
    </table>

    <script>
        var app = new Vue({
            el: '#players',
            data: {
                players: []
            }
        });

        function formatScore(score){
            return (score/1000).toFixed(3);
        }

        function fetchOnlinePlayers() {
            $.get("http://{{$_SERVER['SERVER_ADDR']}}:5200/api/online", function (data) {
                app.players = JSON.parse(data);
            }).fail(function (data) {
                console.log(data);
            });

            setTimeout('fetchOnlinePlayers()', 1000);
        }

        fetchOnlinePlayers();

        console.log('script finished');
    </script>
@endsection