<div class="auth-state-icon">@include('pages.dictionary.icon', ['name' => 'check'])</div>
<span class="eyebrow">MẬT KHẨU ĐÃ ĐỔI</span>
<h2>{{ $page['title'] }}</h2>
<p>{{ $page['description'] }}</p>
<div class="notice-box">Mật khẩu tài khoản của bạn đã được cập nhật an toàn.</div>
<a class="button button-primary button-full" href="{{ route('login') }}">Quay lại đăng nhập</a>
