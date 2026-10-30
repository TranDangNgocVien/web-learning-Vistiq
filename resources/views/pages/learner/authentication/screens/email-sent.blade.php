<div class="auth-state-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'check'])</div>
<span class="eyebrow">KIỂM TRA HỘP THƯ</span>
<h2>Kiểm tra hộp thư</h2>
<p role="status">Đã tiếp nhận yêu cầu đặt lại mật khẩu của bạn.</p>
<div class="notice-box">
    Nếu email bạn nhập có tài khoản Vistiq, liên kết đặt lại mật khẩu sẽ được gửi đến hộp thư đó.
    <p>Kiểm tra cả thư mục Spam / Thư rác. Liên kết có hiệu lực trong {{ config('auth.passwords.users.expire', 60) }} phút và chỉ dùng được một lần.</p>
</div>
<a class="button button-primary button-full" href="{{ route('login') }}">Quay lại đăng nhập</a>
<p class="form-footnote">Chưa nhận được email? Đợi ít phút hoặc kiểm tra lại địa chỉ đã nhập.</p>
<a class="text-link auth-resend" href="{{ route('password.request') }}">Nhập lại email / Gửi lại yêu cầu</a>
