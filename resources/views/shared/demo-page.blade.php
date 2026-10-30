@extends('layouts.app')

@php($hasUnifiedAuth = ($page['shell'] ?? 'user') === 'auth' && ($page['slug'] ?? '') !== 'welcome-demo')

@section('content')
    @include('layouts.' . ($page['shell'] ?? 'user'))
@endsection
