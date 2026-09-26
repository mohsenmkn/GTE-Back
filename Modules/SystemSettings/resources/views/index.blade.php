@extends('systemsettings::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('systemsettings.name') !!}</p>
@endsection
