@extends('contract::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('contract.name') !!}</p>
@endsection
