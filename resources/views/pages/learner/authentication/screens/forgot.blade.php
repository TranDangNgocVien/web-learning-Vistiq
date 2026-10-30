@include('pages.learner.authentication.screens.form-feedback')
<a class="auth-back" href="{{ route('login') }}"><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-left'])</span> Quay lại đăng nhập</a>
<span class="eyebrow auth-eyebrow">KHÔI PHỤC TÀI KHOẢN</span>
<h2>Quên mật khẩu?</h2>
<p>Nhập email bạn đã dùng để tạo tài khoản. Chúng tôi sẽ gửi liên kết khôi phục nếu tìm thấy tài khoản phù hợp.</p>

@if (session('status'))
    <div class="auth-alert auth-alert-success" role="status">{{ session('status') }}</div>
@endif

<form data-auth-feedback data-pending-label="Đang gửi yêu cầu…" class="auth-form" action="{{ route('password.email') }}" method="post">
    @csrf

    <div class="auth-field">
        <label for="email">Địa chỉ email</label>
        <div class="auth-input-wrap">
            <svg aria-hidden="true" viewBox="0 0 20 20" focusable="false"><path d="M3.25 5.5A1.75 1.75 0 0 1 5 3.75h10A1.75 1.75 0 0 1 16.75 5.5v9A1.75 1.75 0 0 1 15 16.25H5a1.75 1.75 0 0 1-1.75-1.75zM4 5l6 4.75L16 5" /></svg>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" placeholder="ban@email.com" required autofocus aria-invalid="@error('email') true @else false @enderror" @error('email') aria-describedby="email-error" @enderror>
        </div>
        @error('email')
            <span class="auth-field-error" id="email-error" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <button class="button button-primary button-full auth-submit" type="submit">
        Gửi liên kết khôi phục <span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span>
    </button>
</form>

<p class="form-footnote">Nhớ ra mật khẩu rồi? <a href="{{ route('login') }}">Đăng nhập</a></p>
