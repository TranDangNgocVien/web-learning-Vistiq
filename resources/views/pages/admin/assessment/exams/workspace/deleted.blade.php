<section class="ex-panel">
    <header class="ex-panel-heading">
        <div>
            <h1>Đề thi đã xóa</h1>
            <p>Các đề xóa mềm được ẩn khỏi kho học viên. Khôi phục để đưa đề về trạng thái trước khi xóa.</p>
        </div>
        <a class="ex-button" href="{{ route('admin.exams.index') }}">Quay lại danh sách đề</a>
    </header>

    <div class="ex-table-wrap">
        <table class="ex-table">
            <thead><tr><th>Đề thi</th><th>Kỳ thi</th><th>Trạng thái</th><th>Đã xóa lúc</th><th>Thao tác</th></tr></thead>
            <tbody>
                @forelse($exams as $exam)
                    <tr>
                        <td><b>{{ $exam->title }}</b><small>{{ strtoupper($exam->examType?->code ?? 'EXAM') }}-{{ str_pad((string) $exam->id, 4, '0', STR_PAD_LEFT) }}</small></td>
                        <td>{{ $exam->examType?->name ?? '—' }}</td>
                        <td>{{ $exam->status === 'approved' ? 'Đã duyệt' : ($exam->draft_status === 'review' ? 'Chờ duyệt' : 'Bản nháp') }}</td>
                        <td>{{ $exam->deleted_at?->locale('vi')->diffForHumans() }}</td>
                        <td>
                            @can('content.edit')
                                <form method="post" action="{{ route('admin.exams.restore', $exam->id) }}" data-pending-label="Đang khôi phục…">
                                    @csrf
                                    <button class="ex-button" type="submit">Khôi phục đề thi</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="ex-empty"><span>Không có đề thi đã xóa.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <footer class="ex-pagination">{{ $exams->links() }}</footer>
</section>
