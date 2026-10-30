<label class="profile-section-select form-field" for="profile-section">Mục tài khoản<select id="profile-section" data-profile-section>
@foreach(['profile.show'=>'Hồ sơ','profile.history'=>'Lịch sử làm đề','profile.saved'=>'Mục đã lưu','account.password'=>'Đổi mật khẩu'] as $route=>$label)<option value="{{ route($route) }}" @selected(request()->routeIs($route))>{{ $label }}</option>@endforeach
</select></label>
<nav class="profile-tabs profile-desktop-tabs" aria-label="Tài khoản">
@foreach(['profile.show'=>'Hồ sơ','profile.history'=>'Lịch sử làm đề','profile.saved'=>'Mục đã lưu','account.password'=>'Đổi mật khẩu'] as $route=>$label)<a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>
