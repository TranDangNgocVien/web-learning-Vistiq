@include('pages.admin.identity.users-and-permissions.workspace.appearance')
@php($restoringRoles = old('_identity_form') === 'user-roles')
<div class="identity-workspace" data-identity-workspace>
    <a class="text-link" href="{{ route('admin.users.index') }}">← Danh sách người dùng</a>
    <header class="page-heading">
        <div><h1>{{ $user->name }}</h1><div class="identity-meta"><span>{{ $user->email }}</span><span class="badge {{ $user->status === 'active' ? 'badge-mint' : 'badge-pink' }}">{{ $user->status === 'active' ? 'Đang hoạt động' : 'Đã khóa' }}</span></div></div>
        <div class="identity-meta">Đăng ký {{ $user->created_at?->format('d/m/Y') ?? '—' }} · Phiên v{{ $user->session_version }}</div>
    </header>
    <section class="panel" aria-labelledby="account-actions-title">
        <div class="section-head"><h2 id="account-actions-title">Thao tác tài khoản</h2><span class="muted">Thay đổi ảnh hưởng đến quyền truy cập của người dùng.</span></div>
        <div class="identity-actions">
            @if($user->status === 'active')
                <form method="post" action="{{ route('admin.users.action', [$user, 'lock']) }}" data-confirm="Khóa tài khoản {{ $user->email }}? Người dùng sẽ bị đăng xuất và không thể đăng nhập cho đến khi được mở khóa." data-confirm-title="Khóa tài khoản?" data-confirm-label="Khóa tài khoản" data-pending-label="Đang khóa…">@csrf<button type="submit" class="button button-danger">Khóa tài khoản</button></form>
            @else
                <form method="post" action="{{ route('admin.users.action', [$user, 'unlock']) }}">@csrf<button type="submit" class="button button-primary">Mở khóa tài khoản</button></form>
            @endif
            <form method="post" action="{{ route('admin.users.action', [$user, 'revoke-sessions']) }}" data-confirm="Thu hồi mọi phiên đăng nhập của {{ $user->email }}? Người dùng cần đăng nhập lại trên tất cả thiết bị." data-confirm-title="Thu hồi phiên đăng nhập?" data-confirm-label="Thu hồi mọi phiên" data-pending-label="Đang thu hồi…">@csrf<button type="submit" class="button button-quiet">Thu hồi mọi phiên đăng nhập</button></form>
            <form method="post" action="{{ route('admin.users.action', [$user, 'password-reset']) }}" data-pending-label="Đang gửi email…">@csrf<button type="submit" class="button button-quiet">Gửi email đặt lại mật khẩu</button></form>
        </div>
    </section>
    <div class="identity-details">
        @can('roles.manage')
            <section class="panel" aria-labelledby="user-roles-title"><div class="section-head"><h2 id="user-roles-title">Gán vai trò cho người dùng</h2><span class="muted">Có thể chọn nhiều vai trò.</span></div>
                <form method="post" action="{{ route('admin.users.action', [$user, 'roles']) }}" data-user-roles data-user-name="{{ $user->name }}">
                    @csrf<input type="hidden" name="_identity_form" value="user-roles">
                    <div class="role-permission-options">
                        @foreach($roles as $role)
                            <label class="role-permission-option" for="user-role-{{ $role->id }}"><input id="user-role-{{ $role->id }}" type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($restoringRoles ? in_array($role->id, array_map('intval', (array) old('roles', [])), true) : $user->roles->contains('id', $role->id)) @if($user->roles->contains('id', $role->id))data-original-role="{{ $role->name }}"@endif><span><strong>{{ $role->name }}</strong></span></label>
                        @endforeach
                    </div>
                    @error('roles')<p class="admin-login-field-error">{{ $message }}</p>@enderror
                    <div class="role-permission-form__footer"><span>Gỡ vai trò sẽ thu hồi quyền được cấp từ vai trò đó.</span><button type="submit" class="button button-primary">Lưu vai trò cho người dùng</button></div>
                </form>
            </section>
        @endcan
    </div>
    @can('results.view')
    <div class="identity-details">
        <section class="panel"><div class="section-head"><h2>Lịch sử thi <span class="count-pill">{{ $attempts->count() }}</span></h2><span class="muted">Tối đa 30 lượt gần nhất</span></div><div class="identity-records">
            @forelse($attempts as $attempt)<div class="identity-record"><strong>#{{ $attempt->id }} · {{ $attempt->exam->title ?? 'Bài thi' }}</strong><div class="identity-meta"><span>{{ ['in_progress' => 'Đang làm', 'submitted' => 'Đã nộp', 'graded' => 'Đã chấm', 'expired' => 'Hết thời gian', 'abandoned' => 'Đã rời bài'][$attempt->status] ?? $attempt->status }}</span><span>{{ $attempt->created_at?->format('d/m/Y H:i') }}</span></div></div>@empty<p>Chưa có lượt làm bài nào.</p>@endforelse
        </div></section>
        <section class="panel"><div class="section-head"><h2>Báo cáo liên quan <span class="count-pill">{{ $reports->count() }}</span></h2><span class="muted">Tối đa 50 báo cáo gần nhất</span></div><div class="identity-records">
            @forelse($reports as $report)<div class="identity-record"><div class="identity-meta"><strong>Lượt thi #{{ $report->exam_attempt_id }}</strong><span class="badge badge-soft">{{ ['pending' => 'Chờ xử lý', 'resolved' => 'Đã xử lý', 'dismissed' => 'Đã bỏ qua'][$report->status] ?? $report->status }}</span></div><p>{{ $report->reason }}</p>@if($report->details)<p>{{ $report->details }}</p>@endif</div>@empty<p>Không có báo cáo nào liên quan.</p>@endforelse
        </div></section>
    </div>
    @endcan
</div>
