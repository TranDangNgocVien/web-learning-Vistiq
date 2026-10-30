@include('pages.learner.authentication.screens.form-feedback')
<header class="admin-login-heading"><h1>Xác thực hai bước</h1><p>Nhập mã 6 số được gửi đến email quản trị. Mã dùng một lần và hết hạn sau 5 phút.</p></header>
@if(session('status'))<div class="admin-login-notice" role="status">{{ session('status') }}</div>@endif
<form class="admin-login-form" method="post" action="{{ route('admin.otp.verify') }}" data-auth-feedback data-pending-label="Đang xác thực…">
    @csrf
    <div class="admin-login-field"><label for="admin-otp">Mã xác thực *</label><input id="admin-otp" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="000000" required autofocus aria-describedby="otp-help @error('code')otp-error @enderror" @error('code')aria-invalid="true"@enderror><small id="otp-help" class="muted">Không chia sẻ mã này với bất kỳ ai.</small>@error('code')<p class="admin-login-field-error" id="otp-error" role="alert">{{ $message }}</p>@enderror</div>
    <button type="submit" class="button button-primary button-full">Xác thực và tiếp tục</button>
</form>
<form class="admin-login-form" method="post" action="{{ route('admin.otp.send') }}" data-auth-feedback data-pending-label="Đang gửi mã…" style="margin-top:16px">
    @csrf<button type="submit" class="button button-quiet button-full">Gửi mã mới</button><p class="admin-login-help">Chưa nhận được mã? Kiểm tra thư mục spam. Chỉ gửi lại mỗi 60 giây.</p>
</form>
