@php($authReturnUrl = ($page['slug'] ?? '') === 'account-password' ? route('profile.show') : route('home'))
@php($authReturnLabel = ($page['slug'] ?? '') === 'account-password' ? 'Quay lại hồ sơ' : 'Về trang chủ')

<section class="auth-brand-panel" aria-labelledby="auth-brand-title">
    <a class="auth-brand-logo" href="{{ route('home') }}" aria-label="Vistiq English — trang chủ">
        <img src="{{ asset('images/vistiq-logo.png') }}" alt="Vistiq English" width="260" height="260">
    </a>
    <div class="auth-brand-copy">
        <span>KHÔNG GIAN HỌC TIẾNG ANH</span>
        <h1 id="auth-brand-title">Từ mới mỗi ngày.<br>Tiến bộ từng bước.</h1>
        <p>Tra cứu, luyện tập và lưu lại hành trình học của bạn.</p>
    </div>
</section>

<section class="auth-form-panel" aria-label="Tài khoản Vistiq">
    <a class="auth-unified-close" href="{{ $authReturnUrl }}" aria-label="{{ $authReturnLabel }}">
        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15"/></svg>
    </a>
    <div class="auth-form-wrap">@include($page['view'])</div>
    <div class="auth-footer">
        <span>© {{ date('Y') }} Vistiq English</span>
        <a href="{{ $authReturnUrl }}">{{ $authReturnLabel }} <span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span></a>
    </div>
</section>
