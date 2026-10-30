@include('pages.admin.identity.users-and-permissions.workspace.appearance')
<div class="identity-workspace identity-users-workspace" data-identity-workspace>
    <header class="page-heading">
        <div><h1>Quản lý người dùng</h1><p>Tìm tài khoản và quản lý trạng thái truy cập.</p></div>
        @can('roles.manage')<a class="button button-quiet" href="{{ route('admin.roles.index') }}">Quản lý vai trò</a>@endcan
    </header>
    <section class="panel" aria-label="Bộ lọc người dùng">
        <form method="get" action="{{ route('admin.users.index') }}" class="admin-form-grid identity-filter" data-ajax-filter-form data-ajax-target="#admin-users-results" data-ajax-on-change data-ajax-debounce="350">
            <label for="filter-q">Tên hoặc email<input id="filter-q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Tìm người dùng…" maxlength="255"></label>
            @php($activeFilterCount = (int) ($filters['status'] !== 'all') + (int) filled($filters['role_id']) + (int) filled($filters['created_from']) + (int) filled($filters['created_to']))
            <details class="identity-filter-options" data-mobile-filters data-active-filters="{{ $activeFilterCount }}" open>
                <summary><span>Bộ lọc nâng cao <span class="badge badge-soft" data-mobile-filter-count @if(!$activeFilterCount) hidden @endif>{{ $activeFilterCount }}</span></span><span class="identity-filter-chevron" aria-hidden="true">⌄</span></summary>
                <div class="identity-filter-fields">
            <label for="filter-status">Trạng thái<select id="filter-status" name="status">
                <option value="all" @selected($filters['status'] === 'all')>Tất cả trạng thái</option>
                <option value="active" @selected($filters['status'] === 'active')>Đang hoạt động</option>
                <option value="locked" @selected($filters['status'] === 'locked')>Đã khóa</option>
            </select></label>
            <label for="filter-role">Vai trò<select id="filter-role" name="role_id"><option value="">Tất cả vai trò</option>
                @foreach($roles as $role)<option value="{{ $role->id }}" @selected((int) $filters['role_id'] === $role->id)>{{ $role->name }}</option>@endforeach
            </select></label>
            <label for="filter-created-from">Tạo từ ngày<input id="filter-created-from" name="created_from" type="date" value="{{ $filters['created_from'] }}" @if($filters['created_to'])max="{{ $filters['created_to'] }}"@endif></label>
            <label for="filter-created-to">Đến ngày<input id="filter-created-to" name="created_to" type="date" value="{{ $filters['created_to'] }}" @if($filters['created_from'])min="{{ $filters['created_from'] }}"@endif></label>
            <div class="identity-actions identity-filter-actions"><a class="button button-quiet" data-ajax-filter-reset="#admin-users-results" href="{{ route('admin.users.index') }}">Đặt lại</a><button type="submit" class="button button-primary">Lọc tài khoản</button></div>
                </div>
            </details>
        </form>
    </section>
    <section id="admin-users-results" class="panel identity-table-panel" aria-labelledby="users-list-title" data-ajax-results>
        <div class="section-head identity-table-heading"><h2 id="users-list-title">Danh sách tài khoản</h2><span class="identity-meta">{{ number_format($users->total()) }} người dùng @if($users->total()) · {{ $users->firstItem() }}–{{ $users->lastItem() }}@endif</span></div>
        <div class="identity-table-scroll" role="region" aria-label="Danh sách người dùng" tabindex="0">
            <table class="admin-table" role="table">
                <thead><tr><th scope="col">Người dùng</th><th scope="col">Trạng thái</th><th scope="col">Vai trò</th><th scope="col">Hoạt động gần nhất</th><th scope="col">Ngày tạo</th><th scope="col" class="identity-table-actions">Thao tác</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="identity-user-person"><a class="identity-person" href="{{ route('admin.users.show', $user) }}"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></a></td>
                        <td class="identity-user-status"><span class="badge {{ $user->status === 'active' ? 'badge-mint' : 'badge-pink' }}">{{ $user->status === 'active' ? 'Hoạt động' : 'Đã khóa' }}</span></td>
                        <td class="identity-user-roles" data-label="Vai trò"><div class="tag-row">@forelse($user->roles as $role)<span class="badge badge-soft">{{ $role->name }}</span>@empty<span class="muted">Học viên</span>@endforelse</div></td>
                        <td class="muted identity-user-activity" data-label="Hoạt động gần nhất">{{ $user->last_active_at?->locale('vi')->diffForHumans() ?? 'Chưa ghi nhận' }}</td>
                        <td class="muted identity-user-created" data-label="Ngày tạo">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="identity-table-actions"><a class="button button-quiet" href="{{ route('admin.users.show', $user) }}" aria-label="Xem hồ sơ {{ $user->name }}">Xem hồ sơ</a></td>
                    </tr>
                @empty
                    <tr class="identity-empty-row"><td colspan="6"><div class="identity-empty"><h2>Không tìm thấy người dùng phù hợp</h2><p>Hãy thử thay đổi từ khóa hoặc điều kiện lọc ở trên.</p><a class="button button-quiet" href="{{ route('admin.users.index') }}">Xóa bộ lọc</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="identity-pagination" data-ajax-pagination>{{ $users->onEachSide(1)->links('pages.admin.identity.users-and-permissions.workspace.pagination') }}</div>@endif
    </section>
</div>
