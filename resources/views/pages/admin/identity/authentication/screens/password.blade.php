@include('pages.learner.authentication.screens.form-feedback')
<header class="admin-login-heading"><h1>{{ isset($token) ? 'Tạo mật khẩu mới' : 'Khôi phục mật khẩu' }}</h1><p>{{ isset($token) ? 'Đặt mật khẩu mới để đăng nhập lại vào hệ thống quản trị.' : 'Nhập email quản trị để nhận liên kết đặt lại mật khẩu.' }}</p></header>
@if(session('status'))<div class="admin-login-notice" role="status">{{ session('status') }} Kiểm tra cả thư mục spam.</div>@endif
<form class="admin-login-form" method="post" action="{{ route(isset($token) ? 'admin.password.update' : 'admin.password.email') }}" data-auth-feedback data-pending-label="{{ isset($token) ? 'Đang đổi mật khẩu…' : 'Đang gửi liên kết…' }}">
    @csrf
    <div class="admin-login-field"><label for="recovery-email">Email quản trị *</label><input id="recovery-email" type="email" name="email" value="{{ old('email', $email ?? '') }}" required maxlength="254" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" placeholder="admin@example.com" @error('email')aria-invalid="true" aria-describedby="recovery-email-error"@enderror>@error('email')<p class="admin-login-field-error" id="recovery-email-error">{{ $message }}</p>@enderror</div>
    @if(isset($token))
        <input type="hidden" name="token" value="{{ $token }}">
        @error('token')<p class="admin-login-field-error" role="alert">{{ $message }} <a href="{{ route('admin.password.request') }}">Yêu cầu liên kết mới</a></p>@enderror
        <div class="admin-login-field"><label for="recovery-password">Mật khẩu mới *</label><input id="recovery-password" name="password" type="password" minlength="10" maxlength="128" required autocomplete="new-password" aria-describedby="recovery-password-help @error('password')recovery-password-error @enderror" @error('password')aria-invalid="true"@enderror><small id="recovery-password-help" class="muted">Từ 10 đến 128 ký tự.</small>@error('password')<p id="recovery-password-error" class="admin-login-field-error">{{ $message }}</p>@enderror</div>
        <div class="admin-login-field"><label for="recovery-password-confirm">Nhập lại mật khẩu *</label><input id="recovery-password-confirm" name="password_confirmation" type="password" minlength="10" maxlength="128" required autocomplete="new-password"></div>
    @endif
    <button class="button button-primary button-full" type="submit">{{ isset($token) ? 'Đổi mật khẩu' : 'Gửi liên kết khôi phục' }}</button>
</form>
@if(isset($token))<a class="text-link" href="{{ route('admin.password.request') }}">Liên kết hết hạn? Gửi yêu cầu mới</a>@endif
<a class="text-link" href="{{ route('admin.login') }}">← Quay lại đăng nhập</a>
