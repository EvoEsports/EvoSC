@extends('layout')

@section('content')
    <form class="uk-form-horizontal">
        <div>
            <div class="uk-form-label"><h4>Name</h4></div>
            <div class="uk-form-controls uk-form-controls-text">
                <div class="uk-margin">
                    <input class="uk-input" type="text" value="{{$group->Protected ? '🔒' : ''}} {{$group->Name}}" {{$group->Protected ? 'readonly' : ''}}>
                </div>
            </div>
        </div>
        <div>
            <div class="uk-form-label"><h4>Access rights</h4></div>
            <div class="uk-form-controls uk-form-controls-text">
                <div class="uk-margin">
                    <div class="uk-grid uk-child-width-1-2" uk-grid>
                        @foreach($rights as $right)
                            <div class="uk-margin-small-bottom">
                                <label><input class="uk-checkbox" type="checkbox" {{$access->where('access_right_id', $right->id)->isNotEmpty() ? 'checked' : ''}}> {{$right->description}}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div>
            <div class="uk-form-label"></div>
            <div class="uk-form-controls uk-form-controls-text">
                <div class="uk-margin">
                    <button class="uk-button uk-button-primary">Save</button>
                    <a href="{{url('groups')}}"><button type="button" class="uk-button uk-button-default">Cancel</button></a>
                </div>
            </div>
        </div>
    </form>
@endsection