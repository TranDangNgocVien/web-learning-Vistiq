@php
    $sidebarNavigationGroups = [
        [
            'label' => 'Nội dung',
            'icon' => 'book-open',
            'items' => [
                ['route' => 'admin.content-approval.index', 'permission' => 'content.review', 'label' => 'Duyệt nội dung', 'icon' => 'clipboard-check', 'active' => ['admin.content-approval.*']],
                ['route' => 'admin.questions.index', 'permission' => 'content.view', 'label' => 'Ngân hàng Câu Hỏi', 'icon' => 'book-open-text', 'active' => ['admin.questions.index'], 'children' => [
                    ['route' => 'admin.questions.listening', 'permission' => 'content.view', 'label' => 'Câu hỏi Listening', 'active' => ['admin.questions.listening']],
                    ['route' => 'admin.readings.index', 'permission' => 'content.view', 'label' => 'Câu hỏi Reading', 'active' => ['admin.readings.*']],
                    ['route' => 'admin.questions.writing', 'permission' => 'content.view', 'label' => 'Câu hỏi Writing', 'active' => ['admin.questions.writing', 'admin.questions.writing.*']],
                    ['route' => 'admin.questions.speaking', 'permission' => 'content.view', 'label' => 'Câu hỏi Speaking', 'active' => ['admin.questions.speaking', 'admin.questions.speaking.*']],
                    ['route' => 'admin.questions.thpt', 'permission' => 'content.view', 'label' => 'Câu hỏi Tiếng Anh THPT', 'active' => ['admin.questions.thpt']],
                ]],
                ['route' => 'admin.resources.index', 'permission' => 'content.view', 'label' => 'Tài nguyên', 'icon' => 'resources', 'active' => ['admin.resources.*', 'admin.audio.*', 'admin.media.*']],
                ['route' => 'admin.knowledge.index', 'permission' => 'content.view', 'label' => 'Kho kiến thức', 'icon' => 'library', 'active' => ['admin.knowledge.*']],
            ],
        ],
        [
            'label' => 'Thi & đánh giá',
            'icon' => 'exam',
            'items' => [
                ['route' => 'admin.exams.index', 'permission' => 'content.view', 'label' => 'Đề thi', 'icon' => 'file-check', 'active' => ['admin.exams.*']],
                ['route' => 'admin.attempts.index', 'permission' => 'results.view', 'label' => 'Lượt thi & báo cáo', 'icon' => 'activity', 'active' => ['admin.attempts.*']],
                ['route' => 'admin.leaderboard.index', 'permission' => 'results.view', 'label' => 'Leaderboard', 'icon' => 'trophy', 'active' => ['admin.leaderboard.*']],
                ['route' => 'admin.analytics', 'permission' => 'results.view', 'label' => 'Analytics', 'icon' => 'chart-column', 'active' => ['admin.analytics']],
            ],
        ],
        [
            'label' => 'Quản trị',
            'icon' => 'settings-2',
            'items' => [
                ['route' => 'admin.users.index', 'permission' => 'users.manage', 'label' => 'Người dùng', 'icon' => 'users', 'active' => ['admin.users.*']],
                ['route' => 'admin.roles.index', 'permission' => 'roles.manage', 'label' => 'Phân quyền', 'icon' => 'shield-check', 'active' => ['admin.roles.*']],
                ['route' => 'admin.settings.index', 'permission' => 'settings.manage', 'label' => 'Settings', 'icon' => 'settings', 'active' => ['admin.settings.*']],
                ['route' => 'admin.audit.index', 'permission' => 'audit.view', 'label' => 'Audit log', 'icon' => 'history', 'active' => ['admin.audit.*']],
            ],
        ],
    ];
    $canSeeSidebarItem = static fn (array $navItem): bool => auth()->user()?->hasPermission($navItem['permission']) ?? false;
    $isSidebarItemActive = static fn (array $navItem): bool => request()->routeIs(...$navItem['active'])
        || (in_array($editorSkill ?? null, ['writing', 'speaking'], true)
            && $navItem['route'] === 'admin.questions.'.$editorSkill
            && request()->routeIs('admin.questions.create', 'admin.questions.edit'));
    $sidebarNavigationGroups = collect($sidebarNavigationGroups)->map(function (array $navGroup) use ($canSeeSidebarItem, $isSidebarItemActive): array {
        $navGroup['items'] = collect($navGroup['items'])->map(function (array $navItem) use ($canSeeSidebarItem, $isSidebarItemActive): ?array {
            if (isset($navItem['children'])) {
                $navItem['children'] = collect($navItem['children'])->filter($canSeeSidebarItem)->values();
                $navItem['is_active'] = $navItem['children']->contains(fn (array $navChild): bool => $isSidebarItemActive($navChild));
                return $canSeeSidebarItem($navItem) && $navItem['children']->isNotEmpty() ? $navItem : null;
            }

            return $canSeeSidebarItem($navItem) ? $navItem : null;
        })->filter()->values();
        $navGroup['is_active'] = $navGroup['items']->contains(fn (array $navItem): bool => $isSidebarItemActive($navItem) || ($navItem['is_active'] ?? false));

        return $navGroup;
    })->filter(fn (array $navGroup): bool => $navGroup['items']->isNotEmpty())->values();
@endphp
@php
    $accountUser = auth()->user();
    $accountAvatarPath = $accountUser?->avatar_path;
    $accountAvatar = $accountAvatarPath
        && str_starts_with($accountAvatarPath, 'avatars/'.$accountUser->id.'/')
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($accountAvatarPath)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($accountAvatarPath)
            : null;
    $accountInitial = mb_strtoupper(mb_substr(trim($accountUser?->name ?? 'Admin'), 0, 1));
@endphp
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="brand brand-light" href="{{ route('admin.dashboard') }}"><img class="brand-mark" src="{{ asset('images/vistiq-logo.png') }}" alt="Vistiq"></a>
        <div class="sidebar-caption">ADMIN STUDIO</div>
        <nav class="admin-nav" aria-label="Điều hướng quản trị">
            @can('admin.access')
                <a class="admin-nav-overview {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}" aria-label="Tổng quan" title="Tổng quan" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    <span class="admin-nav-link-icon">@include('pages.dictionary.icon', ['name' => 'dashboard'])</span><span class="admin-nav-link-label">Tổng quan</span>
                </a>
            @endcan
            @foreach($sidebarNavigationGroups as $navGroup)
                <details class="admin-nav-group {{ $navGroup['is_active'] ? 'is-active' : '' }}" data-sidebar-nav-group @if($navGroup['is_active']) open @endif>
                    <summary title="{{ $navGroup['label'] }}" aria-label="{{ $navGroup['label'] }}">
                        <span class="admin-nav-group-icon">@include('pages.dictionary.icon', ['name' => $navGroup['icon']])</span>
                        <span class="admin-nav-group-label">{{ $navGroup['label'] }}</span>
                    </summary>
                    <div class="admin-nav-group-items">
                        @foreach($navGroup['items'] as $navItem)
                            @php($itemIsActive = $isSidebarItemActive($navItem))
                            @if(isset($navItem['children']))
                                <details class="admin-nav-subgroup {{ $navItem['is_active'] ? 'is-active' : '' }}" @if($navItem['is_active']) open @endif>
                                    <summary title="{{ $navItem['label'] }}" aria-label="{{ $navItem['label'] }}"><span class="admin-nav-link-icon">@include('pages.dictionary.icon', ['name' => $navItem['icon']])</span><span class="admin-nav-link-label">{{ $navItem['label'] }}</span></summary>
                                    <div class="admin-nav-subgroup-items">
                                        @foreach($navItem['children'] as $navChild)
                                            @php($childIsActive = $isSidebarItemActive($navChild))
                                            <a class="{{ $childIsActive ? 'is-active' : '' }}" href="{{ route($navChild['route']) }}" aria-label="{{ $navChild['label'] }}" title="{{ $navChild['label'] }}" @if($childIsActive) aria-current="page" @endif><span class="admin-nav-link-label">{{ $navChild['label'] }}</span></a>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <a class="{{ $itemIsActive ? 'is-active' : '' }}" href="{{ route($navItem['route']) }}" aria-label="{{ $navItem['label'] }}" title="{{ $navItem['label'] }}" @if($itemIsActive) aria-current="page" @endif>
                                    <span class="admin-nav-link-icon">@include('pages.dictionary.icon', ['name' => $navItem['icon']])</span>
                                    <span class="admin-nav-link-label">{{ $navItem['label'] }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </details>
            @endforeach
        </nav>
        <div class="sidebar-bottom"><p>{{ auth()->user()?->name }}</p></div>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="breadcrumbs"><a href="{{ route('admin.dashboard') }}">Admin Studio</a></div>
            <details class="admin-account-menu" data-admin-account-menu>
                <summary aria-label="Tài khoản của {{ $accountUser?->name }}" title="Tài khoản">
                    <span class="avatar" aria-hidden="true">
                        @if($accountAvatar)<img src="{{ $accountAvatar }}" alt="">@else{{ $accountInitial }}@endif
                    </span>
                </summary>
                <div class="admin-account-panel">
                    <div class="admin-account-identity"><strong>{{ $accountUser?->name }}</strong><span>{{ $accountUser?->email }}</span></div>
                    <form method="post" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="button button-quiet admin-account-logout">Đăng xuất</button>
                    </form>
                </div>
            </details>
        </header>
        <main class="page-wrap admin-page">@include('shared.feedback') @unless(request()->routeIs('admin.search'))<div id="admin-search-results" data-ajax-results hidden></div>@endunless @include($page['view'])</main>
    </div>
</div>
