@extends('layout')

@section('content')
    <table class="uk-table uk-table-expand uk-table-small uk-table-striped">
        <thead>
        <th>Name</th>
        <th></th>
        </thead>
        <tbody>
        @foreach($groups as $group)
            <tr>
                <td>{{$group->Name}}</td>
                <td><a href="{{url('groups/'.$group->id.'/edit/')}}">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection