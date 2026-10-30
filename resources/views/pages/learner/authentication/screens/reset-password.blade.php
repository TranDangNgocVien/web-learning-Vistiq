@include('pages.learner.authentication.screens.form-feedback')
<a class="auth-back" href="{{ route('password.request') }}"><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-left'])</span> Yêu cầu liên kết mới</a>
<span class="eyebrow auth-eyebrow">CẬP NHẬT BẢO MẬT</span>
<h2>Tạo mật khẩu mới</h2>
<p>Chọn mật khẩu mới có ít nhất 8 ký tự, gồm cả chữ và số.</p>
@error('token')<div class="auth-alert" role="alert">Liên kết không hợp lệ. <a href="{{ route('password.request') }}">Yêu cầu liên kết mới</a>.</div>@enderror

<form data-auth-feedback data-pending-label="Đang cập nhật…" class="auth-form" action="{{ route('password.update') }}" method="post">
    @csrf
    <input type="hidden" name="token" value="{{ old('token', $page['token'] ?? '') }}">

    <div class="auth-field">
        <label for="email">Địa chỉ email</label>
        <div class="auth-input-wrap">
            <svg aria-hidden="true" viewBox="0 0 20 20" focusable="false"><path d="M3.25 5.5A1.75 1.75 0 0 1 5 3.75h10A1.75 1.75 0 0 1 16.75 5.5v9A1.75 1.75 0 0 1 15 16.25H5a1.75 1.75 0 0 1-1.75-1.75zM4 5l6 4.75L16 5" /></svg>
            <input id="email" name="email" type="email" value="{{ old('email', $page['email'] ?? '') }}" autocomplete="email" inputmode="email" placeholder="ban@email.com" required autofocus aria-invalid="@error('email') true @else false @enderror" @error('email') aria-describedby="email-error" @enderror>
        </div>
        @error('email')
            <span class="auth-field-error" id="email-error" role="alert">{{ $message }}</span>
        @enderror
    </div>

    @include('pages.auth.password-field', [
        'fieldId' => 'password', 'fieldLabel' => 'Mật khẩu mới', 'fieldAutocomplete' => 'new-password',
        'fieldPlaceholder' => 'Ít nhất 8 ký tự, gồm chữ và số', 'showForgotLink' => false,
        'hasPasswordRules' => true, 'minlength' => 8,
    ])
    @include('pages.auth.password-field', [
        'fieldId' => 'password_confirmation', 'fieldLabel' => 'Nhập lại mật khẩu mới',
        'fieldAutocomplete' => 'new-password', 'fieldPlaceholder' => 'Nhập lại mật khẩu',
        'showForgotLink' => false,
    ])
    <p class="auth-password-help" id="auth-password-help">Dùng biểu tượng con mắt để kiểm tra mật khẩu trước khi lưu.</p>

    <button class="button button-primary button-full auth-submit" type="submit">
        Đổi mật khẩu <span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span>
    </button>
</form>
