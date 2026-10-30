@include('pages.learner.authentication.screens.form-feedback')
<span class="eyebrow auth-eyebrow">VISTIQ ENGLISH <span>/</span> TÀI KHOẢN MỚI</span>
<h2>Tạo tài khoản</h2>
<p>Bắt đầu hành trình học tiếng Anh của bạn.</p>

<form data-auth-feedback data-pending-label="Đang tạo tài khoản…" class="auth-form" action="{{ route('register.store') }}" method="post">
    @csrf

    <div class="auth-field">
        <label for="name">Họ và tên</label>
        <div class="auth-input-wrap">
            <svg aria-hidden="true" viewBox="0 0 20 20" focusable="false"><circle cx="10" cy="6.5" r="3"/><path d="M3.75 16a6.25 6.25 0 0 1 12.5 0"/></svg>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" placeholder="Tên của bạn" required autofocus aria-invalid="@error('name') true @else false @enderror" @error('name') aria-describedby="name-error" @enderror>
        </div>
        @error('name')
            <span class="auth-field-error" id="name-error" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <div class="auth-field">
        <label for="email">Địa chỉ email</label>
        <div class="auth-input-wrap">
            <svg aria-hidden="true" viewBox="0 0 20 20" focusable="false"><path d="M3.25 5.5A1.75 1.75 0 0 1 5 3.75h10A1.75 1.75 0 0 1 16.75 5.5v9A1.75 1.75 0 0 1 15 16.25H5a1.75 1.75 0 0 1-1.75-1.75zM4 5l6 4.75L16 5" /></svg>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" placeholder="Địa chỉ email" required aria-invalid="@error('email') true @else false @enderror" @error('email') aria-describedby="email-error" @enderror>
        </div>
        @error('email')
            <span class="auth-field-error" id="email-error" role="alert">{{ $message }}</span>
        @enderror
    </div>

    @include('pages.auth.password-field', [
        'fieldId' => 'password',
        'fieldLabel' => 'Mật khẩu',
        'fieldAutocomplete' => 'new-password',
        'fieldPlaceholder' => 'Ít nhất 8 ký tự, gồm chữ và số',
        'showForgotLink' => false,
        'hasPasswordRules' => true,
        'minlength' => 8,
    ])

    @include('pages.auth.password-field', [
        'fieldId' => 'password_confirmation',
        'fieldLabel' => 'Nhập lại mật khẩu',
        'fieldAutocomplete' => 'new-password',
        'fieldPlaceholder' => 'Nhập lại mật khẩu',
        'showForgotLink' => false,
    ])
    <p class="auth-password-help" id="auth-password-help">Mật khẩu có ít nhất 8 ký tự, gồm chữ và số. Dùng biểu tượng con mắt để kiểm tra.</p>

    <button class="button button-primary button-full auth-submit" type="submit">
        Tạo tài khoản <span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span>
    </button>
</form>

<p class="form-footnote">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
