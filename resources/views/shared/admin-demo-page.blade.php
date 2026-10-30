@extends('layouts.admin-app')

@section('content')
    @include(($page['shell'] ?? 'admin') === 'auth' ? 'layouts.admin-auth' : 'layouts.admin')
@endsection
