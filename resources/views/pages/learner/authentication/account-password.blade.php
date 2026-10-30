@include('pages.learner.authentication.screens.form-feedback')
<a class="auth-back" href="{{ route('profile.show') }}"><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-left'])</span> Quay lại hồ sơ</a>
<span class="eyebrow auth-eyebrow">BẢO MẬT TÀI KHOẢN</span>
<h2>Đổi mật khẩu</h2>
<p>Chọn mật khẩu mới có ít nhất 8 ký tự, gồm chữ và số. Các phiên đăng nhập khác sẽ bị thu hồi.</p>

<form data-auth-feedback data-pending-label="Đang cập nhật…" class="auth-form" method="post" action="{{ route('account.password.update') }}">
    @csrf
    @method('PUT')

    @include('pages.auth.password-field', [
        'fieldId' => 'current_password', 'fieldLabel' => 'Mật khẩu hiện tại',
        'fieldAutocomplete' => 'current-password', 'fieldPlaceholder' => 'Nhập mật khẩu hiện tại',
        'showForgotLink' => false,
    ])
    @include('pages.auth.password-field', [
        'fieldId' => 'password', 'fieldLabel' => 'Mật khẩu mới',
        'fieldAutocomplete' => 'new-password', 'fieldPlaceholder' => 'Ít nhất 8 ký tự, gồm chữ và số',
        'showForgotLink' => false, 'hasPasswordRules' => true, 'minlength' => 8,
    ])
    @include('pages.auth.password-field', [
        'fieldId' => 'password_confirmation', 'fieldLabel' => 'Nhập lại mật khẩu mới',
        'fieldAutocomplete' => 'new-password', 'fieldPlaceholder' => 'Nhập lại mật khẩu mới',
        'showForgotLink' => false,
    ])
    <p class="auth-password-help" id="auth-password-help">Dùng biểu tượng con mắt để kiểm tra mật khẩu trước khi lưu.</p>

    <button class="button button-primary button-full auth-submit" type="submit">
        Đổi mật khẩu <span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span>
    </button>
</form>
