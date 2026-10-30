<header class="admin-login-heading">
    <span class="admin-auth-kicker">CHÀO MỪNG TRỞ LẠI</span>
    <h1>Đăng nhập quản trị</h1>
    <p>Sử dụng tài khoản được cấp quyền để tiếp tục.</p>
</header>
@if(session('status'))
    <div class="admin-login-notice" role="status">{{ session('status') }}</div>
@endif
@if($errors->any() && ! $errors->has('email') && ! $errors->has('password'))
    <div class="admin-login-error" role="alert">{{ $errors->first() }}</div>
@endif
<form class="admin-login-form" action="{{ route('admin.login.store') }}" method="post" data-admin-login-form>
    @csrf
    <div class="admin-login-field">
        <label for="admin-email">Email quản trị</label>
        <input id="admin-email" name="email" type="email" value="{{ old('email') }}" placeholder="Nhập email của bạn" autocomplete="username" autocapitalize="none" spellcheck="false" required @if($errors->has('email'))aria-invalid="true" aria-describedby="admin-email-error"@endif>
        @error('email')<p id="admin-email-error" class="admin-login-field-error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="admin-login-field">
        <label for="admin-password">Mật khẩu</label>
        <div class="admin-login-password">
            <input id="admin-password" name="password" type="password" placeholder="Nhập mật khẩu" autocomplete="current-password" required @if($errors->has('password'))aria-invalid="true" aria-describedby="admin-password-error"@endif>
            <button class="admin-login-toggle" type="button" data-password-toggle aria-controls="admin-password" aria-label="Hiện mật khẩu" aria-pressed="false">@include('pages.dictionary.icon', ['name' => 'eye'])</button>
        </div>
        @error('password')<p id="admin-password-error" class="admin-login-field-error" role="alert">{{ $message }}</p>@enderror
        <p class="admin-login-caps" data-caps-lock hidden role="status">Caps Lock đang bật.</p>
    </div>
    <div class="admin-login-options">
        <label class="admin-login-remember"><input name="remember" type="checkbox" value="1" @checked(old('remember'))><span>Giữ đăng nhập</span></label>
        <a href="{{ route('admin.password.request') }}">Quên mật khẩu?</a>
    </div>
    <button class="admin-login-submit" type="submit"><span data-login-button-label>Đăng nhập</span><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span></button>
    <p class="admin-login-help">Cần cấp quyền truy cập? Liên hệ quản trị hệ thống.</p>
</form>
