@include('pages.admin.system.workspace.nav')
<style>
    #audit-filter-form.audit-filter-panel { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)) auto; align-items:end; gap:12px; padding:14px; }
    #audit-filter-form.audit-filter-panel > label { display:flex; flex-direction:column; gap:6px; min-width:0; margin:0; }
    #audit-filter-form.audit-filter-panel input,
    #audit-filter-form.audit-filter-panel select { width:100%; min-width:0; height:38px; padding:0 10px; border:1px solid var(--line); border-radius:8px; background:#fff; color:#4e607c; font-size:11px; }
    #audit-filter-form.audit-filter-panel .audit-filter-reset { justify-self:start; white-space:nowrap; }
    @media (max-width:900px) {
        #audit-filter-form.audit-filter-panel { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:560px) {
        #audit-filter-form.audit-filter-panel { grid-template-columns:minmax(0,1fr); }
    }
</style>
<form id="audit-filter-form" class="filter-panel audit-filter-panel" method="get" data-ajax-filter-form data-ajax-target="#audit-results" data-ajax-on-change data-ajax-debounce="350">
    <label for="audit-filter-from">Từ ngày
        <input id="audit-filter-from" name="from" value="{{ $filters['from']??'' }}" type="date">
    </label>
    <label for="audit-filter-to">Đến ngày
        <input id="audit-filter-to" name="to" value="{{ $filters['to']??'' }}" type="date">
    </label>
    <label for="audit-filter-result">Kết quả
        <select id="audit-filter-result" name="result">
            <option value="">Tất cả</option>
            @foreach(['success'=>'Thành công','failure'=>'Thất bại','pending'=>'Đang xử lý'] as $key=>$label)
                <option value="{{ $key }}" @selected(($filters['result']??'')===$key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <a class="button button-quiet audit-filter-reset" data-ajax-filter-reset="#audit-results" href="{{ route('admin.audit.index') }}">Xóa bộ lọc</a>
</form>
<section id="audit-results" class="panel table-responsive" data-ajax-results>
    <p>{{ $logs->total() }} sự kiện · Múi giờ {{ config('app.timezone') }}</p>
    <table class="admin-table">
        <thead><tr><th>Thời điểm</th><th>Người thực hiện</th><th>Hành động</th><th>Đối tượng</th><th>Kết quả</th><th>Thay đổi</th></tr></thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at }}</td>
                    <td>{{ $log->actor_name??'Hệ thống / tài khoản đã xóa' }}</td>
                    <td>{{ $log->action_label }}</td>
                    <td>{{ $log->entity_label }}</td>
                    <td>{{ ['success'=>'Thành công','failure'=>'Thất bại','pending'=>'Đang xử lý'][$log->result]??$log->result }}</td>
                    <td>
                        @if($log->diff)
                            <details>
                                <summary>Xem {{ count($log->diff) }} thay đổi</summary>
                                <div class="table-responsive">
                                    <table class="admin-table audit-diff-table">
                                        <thead><tr><th>Trường</th><th>Trước</th><th>Sau</th></tr></thead>
                                        <tbody>
                                            @foreach($log->diff as $change)
                                                <tr><th scope="row">{{ $change['label'] }}</th><td>{{ $change['before'] }}</td><td>{{ $change['after'] }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @else
                            <span>Không có chi tiết thay đổi</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Chưa có sự kiện phù hợp.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div data-ajax-pagination>{{ $logs->links() }}</div>
</section>
