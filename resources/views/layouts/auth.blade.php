@if (($page['slug'] ?? '') === 'welcome-demo')
    <main class="auth-shell auth-intro">@include($page['view'])</main>
@else
    <main class="auth-shell auth-unified">@include('pages.auth.unified-shell')</main>
@endif
