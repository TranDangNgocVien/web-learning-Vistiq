@php
    $rows = $rankings->getCollection();
    $totalRows = (int) $rankings->total();
    $bestScore = $rows->isNotEmpty() ? $rows->max('score') : null;
    $fastestSeconds = $rows->isNotEmpty() ? (int) $rows->min('seconds') : null;
    $currentUserRow = $rows->firstWhere('is_current_user', true);
    $featuredRow = $currentUserRow ?? $rows->first();
    $featuredIsCurrentUser = (bool) ($featuredRow['is_current_user'] ?? false);
    $featuredLabel = $featuredIsCurrentUser
        ? 'Thành tích của bạn'
        : ($rankings->currentPage() === 1 ? 'Dẫn đầu bảng' : 'Hạng cao nhất trang này');

    $formatScore = static function ($score): string {
        $value = (float) $score;
        $decimals = abs($value - round($value)) < 0.00001
            ? 0
            : (abs($value * 10 - round($value * 10)) < 0.00001 ? 1 : 2);

        return number_format($value, $decimals, ',', '.');
    };

    $formatDuration = static function ($seconds): string {
        $seconds = max(0, (int) $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' giờ';
        }

        $parts[] = $minutes.' phút';
        $parts[] = $remainingSeconds.' giây';

        return implode(' ', $parts);
    };
@endphp

<style>
    .lb-page {
        max-width: none;
        margin: 0;
        padding: 10px 0 30px;
        color: var(--ink);
    }
    .lb-page a:focus-visible {
        outline: 3px solid rgba(11, 102, 239, .35);
        outline-offset: 3px;
        border-radius: 5px;
    }
    .lb-breadcrumbs {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 14px;
        color: #8190a8;
        font-size: 11px;
    }
    .lb-breadcrumbs a:hover { color: var(--blue); }
    .lb-breadcrumbs [aria-current="page"] { color: #506582; font-weight: 700; }
    .lb-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 18px;
    }
    .lb-heading h1 {
        margin: 0 0 4px;
        color: var(--ink);
        font-size: clamp(24px, 3vw, 30px);
        letter-spacing: -.045em;
    }
    .lb-heading p { margin: 0; color: #5c6f8d; font-size: 13px; }
    .lb-heading .button { flex: 0 0 auto; }
    .lb-rule-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 20px;
        margin-bottom: 16px;
        padding: 17px 20px;
        border: 1px solid #cfe0fb;
        border-radius: 16px;
        background: linear-gradient(108deg, #edf5ff 0%, #f8fbff 68%, #f1f7ff 100%);
    }
    .lb-rule-copy { display: flex; align-items: flex-start; gap: 13px; min-width: 0; }
    .lb-rule-icon {
        display: grid;
        width: 40px;
        height: 40px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 50%;
        color: var(--blue);
        background: #dceaff;
        font-size: 20px;
    }
    .lb-rule-copy h2 { margin: 0 0 3px; color: #075bd6; font-size: 15px; }
    .lb-rule-copy p { max-width: 760px; margin: 0; color: #617593; font-size: 12px; line-height: 1.65; }
    .lb-release {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 225px;
        padding: 10px 14px;
        border-left: 1px solid #cbdcf4;
    }
    .lb-release-icon {
        display: grid;
        width: 35px;
        height: 35px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: #13a36d;
        font-size: 18px;
    }
    .lb-release strong, .lb-release span { display: block; }
    .lb-release strong { color: #168454; font-size: 12px; }
    .lb-release span { margin-top: 2px; color: #71849e; font-size: 10px; }
    .lb-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }
    .lb-stat {
        display: flex;
        align-items: center;
        gap: 17px;
        min-width: 0;
        min-height: 104px;
        padding: 17px 20px;
        border: 1px solid var(--line);
        border-radius: 15px;
        background: var(--card);
        box-shadow: var(--shadow);
    }
    .lb-stat-icon {
        display: grid;
        width: 55px;
        height: 55px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 50%;
        color: var(--blue);
        background: #e7f0ff;
        font-size: 25px;
    }
    .lb-stat--score .lb-stat-icon { color: #e49a08; background: #fff3d9; }
    .lb-stat--time .lb-stat-icon { color: var(--violet); background: #f1eaff; }
    .lb-stat-copy { display: grid; gap: 3px; min-width: 0; }
    .lb-stat-label { color: #61728f; font-size: 11px; font-weight: 600; }
    .lb-stat-value {
        overflow-wrap: anywhere;
        color: var(--ink);
        font-size: clamp(19px, 2.4vw, 25px);
        font-weight: 800;
        letter-spacing: -.04em;
        line-height: 1.25;
        font-variant-numeric: tabular-nums;
    }
    .lb-stat-value small { color: #62748f; font-size: 12px; font-weight: 600; letter-spacing: 0; }
    .lb-stat-note { color: #8795aa; font-size: 10px; }
    .lb-feature {
        position: relative;
        display: grid;
        grid-template-columns: 104px minmax(0, 1fr);
        align-items: center;
        gap: 20px;
        overflow: hidden;
        min-height: 142px;
        margin-bottom: 16px;
        padding: 20px 26px;
        border: 1px solid #dce9fb;
        border-radius: 17px;
        background: radial-gradient(circle at 12% 50%, rgba(255, 211, 104, .18), transparent 17%), linear-gradient(105deg, #e8f2ff 0%, #fff 39%, #f5f9ff 100%);
    }
    .lb-feature::after {
        position: absolute;
        top: -58px;
        right: -22px;
        width: 190px;
        height: 190px;
        border: 1px solid rgba(99, 153, 235, .14);
        border-radius: 50%;
        box-shadow: 0 0 0 20px rgba(99, 153, 235, .035), 0 0 0 42px rgba(99, 153, 235, .025);
        content: "";
        pointer-events: none;
    }
    .lb-feature-medal {
        position: relative;
        z-index: 1;
        display: grid;
        width: 82px;
        height: 82px;
        place-items: center;
        border: 5px solid rgba(255, 255, 255, .78);
        border-radius: 50%;
        color: #b87606;
        background: linear-gradient(145deg, #ffe99e, #ffc847);
        box-shadow: 0 8px 22px rgba(211, 155, 38, .2);
        font-size: 33px;
    }
    .lb-feature-medal .dictionary-icon { position: absolute; top: 13px; font-size: 17px; }
    .lb-feature-medal strong {
        position: absolute;
        bottom: 11px;
        color: #80530b;
        font-size: 17px;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }
    .lb-feature-identity, .lb-feature-facts { position: relative; z-index: 1; }
    .lb-feature-kicker { margin: 0 0 4px; color: #7184a1; font-size: 9px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .lb-feature-identity h2 { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; margin: 0; color: var(--ink); font-size: 19px; }
    .lb-you-badge { display: inline-flex; align-items: center; min-height: 23px; padding: 3px 9px; border-radius: 999px; color: #075bd6; background: #dceaff; font-size: 10px; font-weight: 800; }
    .lb-feature-facts { display: flex; flex-wrap: wrap; gap: 19px; margin: 8px 0 0; }
    .lb-feature-facts div { display: flex; align-items: baseline; gap: 6px; }
    .lb-feature-facts dt { color: #71829c; font-size: 10px; }
    .lb-feature-facts dd { margin: 0; color: #29446e; font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .lb-feature-facts dd .dictionary-icon { margin-right: 4px; color: #e8a20c; }
    .lb-feature-time .dictionary-icon { color: #4e91f4 !important; }
    .lb-page .lb-table-card { overflow: hidden; margin-bottom: 0; padding: 18px 18px 0; }
    .lb-table-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
    .lb-page .lb-table-title { display: flex; align-items: center; gap: 9px; margin: 0; color: var(--ink); font-size: 16px; }
    .lb-table-title .dictionary-icon { color: var(--blue); font-size: 18px; }
    .lb-range { color: #7a8ba5; font-size: 10px; white-space: nowrap; }
    .lb-table-wrap { overflow-x: auto; border-radius: 11px; }
    .lb-table { width: 100%; border-collapse: separate; border-spacing: 0 3px; color: var(--ink); font-size: 12px; }
    .lb-table th {
        padding: 12px 13px;
        color: #596e91;
        background: #f1f5fb;
        font-size: 11px;
        font-weight: 700;
        text-align: left;
        white-space: nowrap;
    }
    .lb-table th:first-child { width: 82px; border-radius: 10px 0 0 10px; }
    .lb-table th:nth-child(3) { width: 120px; }
    .lb-table th:nth-child(4) { width: 190px; }
    .lb-table th:last-child { width: 165px; border-radius: 0 10px 10px 0; }
    .lb-table td { padding: 12px 13px; border-top: 1px solid #edf2f8; background: #fff; vertical-align: middle; }
    .lb-table tbody tr { transition: background-color .16s ease; }
    .lb-table tbody tr:hover td { background: #f8fbff; }
    .lb-table tbody tr.is-current-user td { border-top-color: #d9e9ff; background: #eaf3ff; }
    .lb-table tbody tr.is-current-user:hover td { background: #e3efff; }
    .lb-rank {
        display: inline-grid;
        min-width: 31px;
        height: 31px;
        place-items: center;
        border: 1px solid #dfe7f1;
        border-radius: 50%;
        color: #667891;
        background: #f7f9fc;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    .lb-rank--gold { border-color: #ffd36a; color: #9e6900; background: #fff3d0; }
    .lb-rank--silver { border-color: #d4dce9; color: #65738a; background: #f0f3f8; }
    .lb-rank--bronze { border-color: #f1c3a0; color: #9d6036; background: #fff0e5; }
    .lb-person { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .lb-avatar {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: linear-gradient(145deg, #8fc1ff, #4b86ea);
        font-size: 20px;
    }
    .lb-person-name { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; min-width: 0; }
    .lb-person-name strong { overflow-wrap: anywhere; color: #203d6b; font-size: 12px; }
    .lb-score, .lb-time { color: #183b74; font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .lb-score { color: #075bd6; font-size: 15px; }
    .lb-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        min-height: 25px;
        padding: 3px 9px;
        border: 1px solid #bde9cf;
        border-radius: 999px;
        color: #21844f;
        background: #effaf3;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }
    .lb-status .dictionary-icon { font-size: 12px; }
    .lb-empty-cell { padding: 0 !important; border: 0 !important; background: transparent !important; }
    .lb-empty {
        display: grid;
        justify-items: center;
        padding: 34px 18px;
        color: #62738d;
        text-align: center;
    }
    .lb-empty-icon { margin-bottom: 8px; color: #6d9de4; font-size: 30px; }
    .lb-empty strong { color: var(--ink); font-size: 13px; }
    .lb-empty p { max-width: 420px; margin: 5px 0 13px; font-size: 11px; }
    .lb-table-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 57px;
        padding: 10px 2px;
        color: #7a8ba5;
        font-size: 10px;
    }
    .lb-pagination { margin-left: auto; }
    .lb-pagination nav { display: flex; align-items: center; gap: 4px; }
    .lb-pagination nav a, .lb-pagination nav span[aria-current="page"] span {
        display: inline-grid;
        min-width: 30px;
        height: 30px;
        place-items: center;
        padding: 0 8px;
        border: 1px solid var(--line);
        border-radius: 7px;
        color: #536783;
        background: #fff;
        font-size: 10px;
    }
    .lb-pagination nav span[aria-current="page"] span { border-color: var(--blue); color: #fff; background: var(--blue); }
    .lb-pagination nav a:hover { border-color: #a8c9f8; color: var(--blue); }
    .lb-sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }

    @media (max-width: 900px) {
        .lb-rule-card { grid-template-columns: minmax(0, 1fr); gap: 10px; }
        .lb-release { min-width: 0; margin-left: 53px; padding: 8px 0 0; border-top: 1px solid #d5e3f6; border-left: 0; }
        .lb-feature { grid-template-columns: 82px minmax(0, 1fr); gap: 14px; }
        .lb-feature-medal { width: 72px; height: 72px; }
        .lb-feature-facts { grid-column: 2; margin-top: -2px; }
    }
    @media (max-width: 680px) {
        .lb-page { padding-top: 4px; }
        .lb-heading { align-items: flex-start; flex-direction: column; gap: 10px; margin-bottom: 14px; }
        .lb-heading .button { min-height: 35px; }
        .lb-rule-card { padding: 14px; }
        .lb-rule-copy { gap: 10px; }
        .lb-rule-icon { width: 34px; height: 34px; font-size: 17px; }
        .lb-release { margin-left: 44px; }
        .lb-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; }
        .lb-stat { min-height: 94px; gap: 10px; padding: 12px; }
        .lb-stat-icon { width: 39px; height: 39px; font-size: 19px; }
        .lb-stat-value { font-size: 19px; }
        .lb-stat--time { grid-column: 1 / -1; }
        .lb-feature { grid-template-columns: 62px minmax(0, 1fr); gap: 11px; min-height: 0; padding: 16px; }
        .lb-feature-medal { width: 58px; height: 58px; border-width: 3px; font-size: 24px; }
        .lb-feature-medal .dictionary-icon { top: 8px; font-size: 13px; }
        .lb-feature-medal strong { bottom: 7px; font-size: 13px; }
        .lb-feature-identity h2 { font-size: 16px; }
        .lb-feature-facts { grid-column: 1 / -1; gap: 14px; margin: 4px 0 0; }
        .lb-page .lb-table-card { padding: 14px 12px 0; }
        .lb-table-heading { align-items: flex-start; flex-direction: column; gap: 3px; }
        .lb-page .lb-table-title { font-size: 14px; }
        .lb-table-wrap { overflow: visible; }
        .lb-table { border-spacing: 0 8px; }
        .lb-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); clip-path: inset(50%); white-space: nowrap; }
        .lb-table tbody { display: block; }
        .lb-table tbody tr { display: grid; grid-template-columns: 42px minmax(0, 1fr); gap: 0 10px; margin-bottom: 8px; padding: 11px; border: 1px solid var(--line); border-radius: 11px; background: #fff; }
        .lb-table tbody tr.is-current-user { border-color: #c8defb; background: #eaf3ff; }
        .lb-table tbody tr.lb-empty-row { display: block; margin: 0; padding: 0; border: 0; background: transparent; }
        .lb-table td { display: block; padding: 0; border: 0; background: transparent !important; }
        .lb-table td:first-child { grid-row: 1 / span 2; padding-top: 2px; }
        .lb-table td:nth-child(2) { grid-column: 2; }
        .lb-table td:nth-child(3), .lb-table td:nth-child(4) { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-top: 7px; }
        .lb-table td:nth-child(3)::before, .lb-table td:nth-child(4)::before { color: #8090a6; content: attr(data-label); font-size: 10px; }
        .lb-table td:last-child { grid-column: 1 / -1; margin-top: 9px; padding-top: 9px; border-top: 1px solid #edf2f8; }
        .lb-table-footer { align-items: flex-start; flex-direction: column; }
        .lb-pagination { margin-left: 0; }
    }
    @media (prefers-reduced-motion: reduce) {
        .lb-page { scroll-behavior: auto; }
        .lb-page *, .lb-page *::before, .lb-page *::after { animation: none !important; transition: none !important; }
    }
</style>

<div class="assessment-page lb-page" aria-labelledby="lb-title">
    <nav class="lb-breadcrumbs" aria-label="Đường dẫn">
        <a href="{{ route('exams.index') }}">Kho đề thi</a>
        <span aria-hidden="true">›</span>
        <a href="{{ route('profile.history') }}">Lượt làm của tôi</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Bảng xếp hạng</span>
    </nav>

    <header class="lb-heading">
        <div>
            <h1 id="lb-title">Bảng xếp hạng</h1>
            <p>{{ $exam->title }}</p>
        </div>
        <a class="button button-quiet" href="{{ route('profile.history') }}">
            @include('pages.dictionary.icon', ['name' => 'clock'])
            Lượt làm của tôi
        </a>
    </header>

    <section class="lb-rule-card" aria-labelledby="lb-rules-title">
        <div class="lb-rule-copy">
            <span class="lb-rule-icon">@include('pages.dictionary.icon', ['name' => 'info'])</span>
            <div>
                <h2 id="lb-rules-title">Cách xếp hạng</h2>
                <p>Mỗi học viên chỉ tính lượt làm tốt nhất. Điểm cao hơn xếp trước; nếu bằng điểm, thời gian ngắn hơn xếp trước. Trùng điểm và thời gian sẽ đồng hạng.</p>
            </div>
        </div>
        <div class="lb-release" role="status">
            <span class="lb-release-icon">@include('pages.dictionary.icon', ['name' => 'check'])</span>
            <div><strong>Kết quả đã chấm xong</strong><span>Các lượt đủ điều kiện được công bố</span></div>
        </div>
    </section>

    <section class="lb-stats" aria-label="Tóm tắt bảng xếp hạng">
        <article class="lb-stat">
            <span class="lb-stat-icon">@include('pages.dictionary.icon', ['name' => 'profile'])</span>
            <div class="lb-stat-copy">
                <span class="lb-stat-label">Tổng thí sinh</span>
                <strong class="lb-stat-value">{{ number_format($totalRows, 0, ',', '.') }} <small>người</small></strong>
                <span class="lb-stat-note">Mỗi học viên tính một lượt tốt nhất</span>
            </div>
        </article>
        <article class="lb-stat lb-stat--score">
            <span class="lb-stat-icon">@include('pages.dictionary.icon', ['name' => 'trophy'])</span>
            <div class="lb-stat-copy">
                <span class="lb-stat-label">Điểm cao nhất · trang này</span>
                <strong class="lb-stat-value">{{ $bestScore !== null ? $formatScore($bestScore) : '—' }} <small>{{ $bestScore !== null ? 'điểm' : '' }}</small></strong>
                <span class="lb-stat-note">Trong {{ $rows->count() }} kết quả đang xem</span>
            </div>
        </article>
        <article class="lb-stat lb-stat--time">
            <span class="lb-stat-icon">@include('pages.dictionary.icon', ['name' => 'clock'])</span>
            <div class="lb-stat-copy">
                <span class="lb-stat-label">Thời gian nhanh nhất · trang này</span>
                <strong class="lb-stat-value">{{ $fastestSeconds !== null ? $formatDuration($fastestSeconds) : '—' }}</strong>
                <span class="lb-stat-note">So sánh trong trang hiện tại</span>
            </div>
        </article>
    </section>

    @if ($featuredRow)
        <section class="lb-feature" aria-label="{{ $featuredLabel }}">
            <div class="lb-feature-medal" aria-hidden="true">
                @include('pages.dictionary.icon', ['name' => 'award'])
                <strong>#{{ $featuredRow['rank'] }}</strong>
            </div>
            <div class="lb-feature-identity">
                <p class="lb-feature-kicker">{{ $featuredLabel }}</p>
                <h2>
                    {{ $featuredRow['name'] }}
                    @if ($featuredIsCurrentUser)<span class="lb-you-badge">Bạn</span>@endif
                </h2>
                <dl class="lb-feature-facts">
                    <div><dt>Điểm</dt><dd>@include('pages.dictionary.icon', ['name' => 'star']) {{ $formatScore($featuredRow['score']) }}</dd></div>
                    <div class="lb-feature-time"><dt>Thời gian</dt><dd>@include('pages.dictionary.icon', ['name' => 'clock']) {{ $formatDuration($featuredRow['seconds']) }}</dd></div>
                </dl>
            </div>
        </section>
    @endif

    <section class="panel lb-table-card" aria-labelledby="lb-table-title">
        <div class="lb-table-heading">
            <h2 class="lb-table-title" id="lb-table-title">
                @include('pages.dictionary.icon', ['name' => 'activity'])
                Bảng xếp hạng chi tiết
            </h2>
            <span class="lb-range">
                @if ($totalRows > 0)
                    Đang xem {{ $rankings->firstItem() }}–{{ $rankings->lastItem() }} trên {{ number_format($totalRows, 0, ',', '.') }} thí sinh
                @else
                    Chưa có kết quả
                @endif
            </span>
        </div>

        <div class="lb-table-wrap">
            <table class="lb-table">
                <caption class="lb-sr-only">Kết quả đã chấm xong và được phép công bố, xếp theo điểm rồi thời gian làm.</caption>
                <thead>
                    <tr>
                        <th scope="col">Hạng</th>
                        <th scope="col">Tên</th>
                        <th scope="col">Điểm</th>
                        <th scope="col">Thời gian làm</th>
                        <th scope="col">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rankings as $row)
                        @php
                            $rankClass = match ((int) $row['rank']) {
                                1 => 'lb-rank--gold',
                                2 => 'lb-rank--silver',
                                3 => 'lb-rank--bronze',
                                default => '',
                            };
                        @endphp
                        <tr @class(['is-current-user' => $row['is_current_user']]) @if ($row['is_current_user']) aria-label="Lượt tốt nhất của bạn" @endif>
                            <td data-label="Hạng"><span class="lb-rank {{ $rankClass }}">{{ $row['rank'] }}</span></td>
                            <td data-label="Tên">
                                <span class="lb-person">
                                    <span class="lb-avatar" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'profile'])</span>
                                    <span class="lb-person-name">
                                        <strong>{{ $row['name'] }}</strong>
                                        @if ($row['is_current_user'])<span class="lb-you-badge">Bạn</span>@endif
                                    </span>
                                </span>
                            </td>
                            <td data-label="Điểm"><strong class="lb-score">{{ $formatScore($row['score']) }}</strong></td>
                            <td data-label="Thời gian làm"><span class="lb-time">{{ $formatDuration($row['seconds']) }}</span></td>
                            <td data-label="Trạng thái"><span class="lb-status">@include('pages.dictionary.icon', ['name' => 'check-circle']) Đã hoàn thành</span></td>
                        </tr>
                    @empty
                        <tr class="lb-empty-row">
                            <td class="lb-empty-cell" colspan="5">
                                <div class="lb-empty">
                                    <span class="lb-empty-icon">@include('pages.dictionary.icon', ['name' => 'award'])</span>
                                    <strong>Chưa có kết quả được công bố</strong>
                                    <p>Bảng sẽ hiển thị lượt làm sau khi chấm xong và được duyệt công khai.</p>
                                    <a class="button button-quiet" href="{{ route('exams.index') }}">Quay lại kho đề thi</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rankings->hasPages())
            <div class="lb-table-footer">
                <span>Thứ hạng được tính theo điểm và thời gian làm.</span>
                <div class="lb-pagination">{{ $rankings->links() }}</div>
            </div>
        @endif
    </section>
</div>
