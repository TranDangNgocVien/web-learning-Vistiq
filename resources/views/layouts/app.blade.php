@php
    $shell = $page['shell'] ?? 'user';
    $section = $page['section'] ?? '';
    $hasUnifiedAuth = $shell === 'auth' && ($page['slug'] ?? '') !== 'welcome-demo';
    $assets = ['resources/css/learner.css'];
    if (isset($preview)) {
        $assets[] = 'resources/css/exam-session.css';
    }
    $assets[] = 'resources/js/learner.js';
@endphp
<!doctype html>
<html lang="vi" class="{{ $shell === 'admin' ? 'admin-document' : '' }} {{ $shell === 'auth' ? 'auth-document' : '' }} {{ $hasUnifiedAuth ? 'auth-unified-document' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $hasUnifiedAuth ? '#edf4fc' : ($shell === 'auth' ? '#08151e' : '#f6f9ff') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/vistiq-logo.png') }}">
    <title>{{ $page['title'] ?? 'Vistiq' }} · Vistiq</title>
    @fonts
    @vite($assets)
</head>
<body class="{{ $shell === 'admin' ? 'admin-mode' : ($shell === 'auth' ? 'auth-mode' : 'user-mode') }} {{ $hasUnifiedAuth ? 'auth-unified-mode' : '' }} {{ $section === 'lookup' ? 'dictionary-mode' : '' }}">
    @yield('content')
    @include('shared.confirmation-dialog')
</body>
</html>
