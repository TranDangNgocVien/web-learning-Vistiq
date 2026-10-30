@include('pages.learner.authentication.screens.form-feedback')
<span class="eyebrow auth-eyebrow">VISTIQ ENGLISH <span>/</span> TÀI KHOẢN</span>
<h2>Đăng nhập</h2>
<p>Tiếp tục hành trình học tiếng Anh của bạn.</p>

@if (session('status'))
    <div class="auth-alert auth-alert-success" role="status">{{ session('status') }}</div>
@endif

<form data-auth-feedback data-pending-label="Đang đăng nhập…" class="auth-form" action="{{ route('login.store') }}" method="post">
    @csrf

    <div class="auth-field">
        <label for="email">Địa chỉ email</label>
        <div class="auth-input-wrap">
            <svg aria-hidden="true" viewBox="0 0 20 20" focusable="false"><path d="M3.25 5.5A1.75 1.75 0 0 1 5 3.75h10A1.75 1.75 0 0 1 16.75 5.5v9A1.75 1.75 0 0 1 15 16.25H5a1.75 1.75 0 0 1-1.75-1.75zM4 5l6 4.75L16 5" /></svg>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" placeholder="Địa chỉ email" required autofocus aria-invalid="@error('email') true @else false @enderror" @error('email') aria-describedby="email-error" @enderror>
        </div>
        @error('email')
            <span class="auth-field-error" id="email-error" role="alert">{{ $message }}</span>
        @enderror
    </div>

    @include('pages.auth.password-field', [
        'fieldId' => 'password',
        'fieldLabel' => 'Mật khẩu',
        'fieldAutocomplete' => 'current-password',
        'fieldPlaceholder' => 'Nhập mật khẩu',
        'showForgotLink' => true,
    ])
    <p class="auth-password-help" id="auth-password-help">Bạn có thể hiện mật khẩu bằng biểu tượng con mắt.</p>

    <div class="auth-inline">
        <label class="check-line" for="remember">
            <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
            <span>Ghi nhớ đăng nhập</span>
        </label>
    </div>

    <button class="button button-primary button-full auth-submit" type="submit">
        Đăng nhập <span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span>
    </button>
</form>

<p class="form-footnote">Chưa có tài khoản? <a href="{{ route('register') }}">Tạo tài khoản miễn phí</a></p>
