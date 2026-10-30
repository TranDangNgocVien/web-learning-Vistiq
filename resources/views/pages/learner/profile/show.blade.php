@php
    $initial = mb_strtoupper(mb_substr($user->name,0,1));
    $selectedTimezone = old('time_zone',$profile?->time_zone ?: config('app.timezone'));
    $timeZones = DateTimeZone::listIdentifiers();
@endphp
<form id="profile-form" class="account-profile" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-pending-label="Đang lưu…" data-avatar-form data-preserve-upload>
    @csrf @method('PUT')
    <header class="account-summary">
        <div class="account-avatar-wrap">
            <span class="account-avatar-fallback" data-avatar-fallback @if($avatar) hidden @endif aria-label="Ảnh đại diện chữ {{ $initial }}">{{ $initial }}</span>
            <img class="account-avatar-photo" data-avatar-preview src="{{ $avatar ?? '' }}" alt="Ảnh đại diện của {{ $user->name }}" @if(!$avatar) hidden @endif>
            <a class="account-avatar-camera" href="#profile-avatar-controls" data-avatar-open aria-label="Cập nhật ảnh đại diện" title="Cập nhật ảnh đại diện">@include('pages.dictionary.icon',['name'=>'camera'])</a>
        </div>
        <div class="account-summary-copy">
            <div class="account-name-row"><h1>{{ $user->name }}</h1><a class="account-edit-name" href="#profile-name" aria-label="Chỉnh sửa tên" title="Chỉnh sửa tên">@include('pages.dictionary.icon',['name'=>'edit'])</a></div>
            <p class="account-email">{{ $user->email }}</p>
            <ul class="account-summary-meta"><li>@include('pages.dictionary.icon',['name'=>'location']) {{ $profile?->country_code ?: 'Chưa cập nhật quốc gia' }}</li><li>{{ $timeZone }}</li><li>Thành viên từ {{ $user->created_at->format('m/Y') }}</li></ul>
            @if($profile?->bio)<p class="account-summary-bio">{{ $profile->bio }}</p>@endif
        </div>
        <a class="button button-quiet account-avatar-action" href="#profile-avatar-controls" data-avatar-open>@include('pages.dictionary.icon',['name'=>'camera']) Cập nhật ảnh đại diện</a>
    </header>
    <details id="profile-avatar-controls" class="account-card account-avatar-controls" @if($errors->has('avatar') || $errors->has('remove_avatar')) open @endif>
        <summary>Ảnh đại diện</summary><div class="account-avatar-fields">
            <label class="form-field" for="avatar">JPEG, PNG hoặc WebP · Tối đa 2 MB<input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-help avatar-error"><small id="avatar-help">Kích thước tối đa 4.096 × 4.096 px. Xem ảnh mới ở đầu trang; ảnh được thay khi lưu thành công. Chọn lại tệp nếu lưu lỗi.</small><span id="avatar-error" class="form-field-error" role="status">@error('avatar'){{ $message }}@enderror</span></label>
            @if($avatar)<label class="account-remove-avatar"><input type="checkbox" name="remove_avatar" value="1" @checked(old('remove_avatar'))> Bỏ ảnh hiện tại khi lưu</label>@endif
            <p class="account-avatar-save-hint">Chọn “Lưu thay đổi” trong phần thông tin cá nhân để áp dụng ảnh.</p>
        </div>
    </details>
    <section class="account-metrics" aria-label="Thống kê cá nhân">
        <a class="account-metric" href="{{ route('profile.history') }}"><span class="account-metric-icon tone-blue">@include('pages.dictionary.icon',['name'=>'activity'])</span><span class="account-metric-copy"><span class="account-metric-label">Tổng lượt làm đề đã nộp</span><strong>{{ number_format($stats['submitted']) }}</strong><span class="account-metric-link">Xem lịch sử →</span></span></a>
        <a class="account-metric" href="{{ route('profile.history') }}" title="Số câu đúng trên tổng số câu trắc nghiệm của các lượt đã chấm hoàn tất"><span class="account-metric-icon tone-green">@include('pages.dictionary.icon',['name'=>'activity'])</span><span class="account-metric-copy"><span class="account-metric-label">Độ chính xác</span><strong>{{ $stats['accuracy']!==null ? $stats['accuracy'].'%' : '—' }}</strong><span class="account-metric-link">{{ $stats['accuracy']!==null ? 'Xem kết quả →' : 'Chưa có bài trắc nghiệm' }}</span></span></a>
        <a class="account-metric" href="{{ $rank ? route('exams.leaderboard',$rank['exam_id']) : route('exams.index') }}" @if($rank) title="{{ $rank['title'] }}" @endif><span class="account-metric-icon tone-amber">@include('pages.dictionary.icon',['name'=>'trophy'])</span><span class="account-metric-copy"><span class="account-metric-label">Thứ hạng tốt nhất</span><strong>{{ $rank ? '#'.$rank['rank'] : '—' }}</strong><span class="account-metric-note">{{ $rank ? 'Trong các đề công bố' : 'Chưa có xếp hạng' }}</span></span></a>
        <a class="account-metric" href="{{ route('profile.saved') }}"><span class="account-metric-icon tone-violet">@include('pages.dictionary.icon',['name'=>'saved'])</span><span class="account-metric-copy"><span class="account-metric-label">Mục đã lưu</span><strong>{{ number_format($stats['saved']) }}</strong><span class="account-metric-link">Xem kho kiến thức →</span></span></a>
    </section>
    <div class="account-columns">
        <section class="account-card account-information" aria-labelledby="account-information-title">
            <div class="account-card-heading"><h2 id="account-information-title">@include('pages.dictionary.icon',['name'=>'profile']) Thông tin cá nhân</h2><a class="button button-quiet account-edit-action" href="#profile-name">@include('pages.dictionary.icon',['name'=>'edit']) Chỉnh sửa</a></div>
            <div class="account-form-fields">
                <label class="form-field" for="profile-name">Họ và tên *<input id="profile-name" name="name" value="{{ old('name',$user->name) }}" maxlength="255" required autocomplete="name" aria-describedby="name-error" @error('name') aria-invalid="true" @enderror><span id="name-error" class="form-field-error">@error('name'){{ $message }}@enderror</span></label>
                <label class="form-field" for="profile-email">Email<input id="profile-email" type="email" value="{{ $user->email }}" disabled aria-describedby="profile-email-help"><small id="profile-email-help">Email được quản lý trong chức năng xác thực.</small></label>
                <label class="form-field" for="profile-country">Mã quốc gia (hai chữ cái, ví dụ VN)<input id="profile-country" name="country_code" value="{{ old('country_code',$profile?->country_code) }}" maxlength="2" placeholder="VN" autocomplete="country" aria-describedby="country_code-error" @error('country_code') aria-invalid="true" @enderror><span id="country_code-error" class="form-field-error">@error('country_code'){{ $message }}@enderror</span></label>
                <label class="form-field" for="profile-timezone">Múi giờ<select id="profile-timezone" name="time_zone" aria-describedby="time_zone-error" @error('time_zone') aria-invalid="true" @enderror>@if(!in_array($selectedTimezone,$timeZones,true))<option value="{{ $selectedTimezone }}" selected>{{ $selectedTimezone }}</option>@endif @foreach($timeZones as $zone)<option value="{{ $zone }}" @selected($selectedTimezone===$zone)>{{ $zone }}</option>@endforeach</select><span id="time_zone-error" class="form-field-error">@error('time_zone'){{ $message }}@enderror</span></label>
                <label class="form-field" for="profile-bio">Giới thiệu<textarea id="profile-bio" name="bio" maxlength="1000" rows="3" aria-describedby="bio-counter bio-error" data-profile-bio>{{ old('bio',$profile?->bio) }}</textarea><span class="account-bio-counter" id="bio-counter"><span data-profile-bio-count>{{ mb_strlen(old('bio',$profile?->bio)??'') }}</span>/1000</span><span id="bio-error" class="form-field-error">@error('bio'){{ $message }}@enderror</span></label>
            </div>
            <div class="account-form-actions"><button type="submit" class="button button-primary">@include('pages.dictionary.icon',['name'=>'save']) Lưu thay đổi</button></div>
        </section>
        <aside class="account-sidebar">
            <section class="account-card" aria-labelledby="account-recent-title"><div class="account-card-heading"><h2 id="account-recent-title">@include('pages.dictionary.icon',['name'=>'trophy']) Hoạt động gần đây</h2><a class="account-text-link" href="{{ route('profile.history') }}">Xem tất cả →</a></div>
                <ul class="account-activity-list">@forelse($recent as $activity)<li><span class="account-activity-icon tone-{{ $activity['tone'] }}">@include('pages.dictionary.icon',['name'=>$activity['icon']])</span><div><a href="{{ $activity['url'] }}">{{ $activity['title'] }}</a><p>{{ $activity['detail'] }}</p></div><time datetime="{{ $activity['date']->toIso8601String() }}">{{ $activity['date']->timezone($timeZone)->format('d/m/Y') }}</time></li>@empty<li class="account-activity-empty"><span class="account-activity-icon tone-blue">@include('pages.dictionary.icon',['name'=>'book-open'])</span><div><strong>Chưa có hoạt động</strong><p>Làm một đề hoặc lưu kiến thức để theo dõi tại đây.</p><a class="account-text-link" href="{{ route('exams.index') }}">Khám phá đề thi →</a></div></li>@endforelse</ul>
            </section>
            <section class="account-card" aria-labelledby="account-extra-title"><div class="account-card-heading"><h2 id="account-extra-title">@include('pages.dictionary.icon',['name'=>'security']) Thông tin khác</h2></div>
                <dl class="account-extra-list"><div><dt>@include('pages.dictionary.icon',['name'=>'exam']) Ngày tham gia</dt><dd>{{ $user->created_at->format('d/m/Y') }}</dd></div><div><dt>@include('pages.dictionary.icon',['name'=>'clock']) Múi giờ hiện tại</dt><dd>{{ $timeZone }} ({{ now()->timezone($timeZone)->format('P') }})</dd></div><div><dt>@include('pages.dictionary.icon',['name'=>'globe']) Ngôn ngữ hiển thị</dt><dd>Tiếng Việt</dd></div><div><dt>@include('pages.dictionary.icon',['name'=>'award']) Trạng thái tài khoản</dt><dd><span class="account-active-status">Tài khoản đang hoạt động</span></dd></div></dl>
                <a class="account-password-link" href="{{ route('account.password') }}">@include('pages.dictionary.icon',['name'=>'security']) Đổi mật khẩu →</a>
            </section>
        </aside>
    </div>
</form>
