<section class="admin-auth-brand" aria-labelledby="admin-auth-brand-title">
    <div class="admin-auth-identity">
        <img src="{{ asset('images/vistiq-logo.png') }}" width="56" height="56" alt="Vistiq">
        <div><strong>Vistiq</strong><span>HỆ THỐNG QUẢN TRỊ</span></div>
    </div>
    <div class="admin-auth-intro">
        <span class="admin-auth-kicker">NỘI DUNG & KHẢO THÍ</span>
        <h2 id="admin-auth-brand-title">Quản trị nội dung<br>và kỳ thi.</h2>
        <p>Biên soạn nội dung, tổ chức kỳ thi và quản lý kết quả học tập.</p>
        <div class="admin-auth-workflow" aria-label="Quy trình quản lý nội dung">
            <span>Biên soạn</span><span aria-hidden="true">→</span><span>Duyệt</span><span aria-hidden="true">→</span><span>Xuất bản</span>
        </div>
        <ul class="admin-auth-capabilities">
            <li><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'collection'])</span><div><strong>Kho nội dung & đề thi</strong><p>Câu hỏi, kiến thức và tài nguyên dùng chung.</p></div></li>
            <li><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'activity'])</span><div><strong>Kết quả & báo cáo</strong><p>Chấm bài, theo dõi và phân tích hoạt động.</p></div></li>
        </ul>
    </div>
    <div class="admin-auth-access-note"><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'security'])</span><span>Quyền truy cập được quản lý theo vai trò.</span></div>
</section>
<section class="admin-auth-panel" aria-label="Đăng nhập hệ thống quản trị">
    <div class="admin-auth-panel-header"><span>Cổng quản trị</span><span class="admin-auth-version">Vistiq Admin</span></div>
    <div class="admin-auth-content">
        @include($page['view'], ['token' => $page['token'] ?? null, 'email' => $page['email'] ?? null])
    </div>
    <footer class="admin-auth-footer"><span>© {{ date('Y') }} Vistiq</span><span>Dành cho nhân sự được cấp quyền</span></footer>
</section>
