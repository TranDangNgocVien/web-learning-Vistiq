<div class="question-bank">
    <header class="qb-heading listening-heading">
        <div>
            <h1>Câu hỏi đã xóa</h1>
            <p>Khôi phục câu hỏi để đưa lại vào ngân hàng. Câu được khôi phục không tự thêm vào đề nào.</p>
        </div>
        <div class="qb-heading-actions">
            <a class="button button-quiet" href="{{ route('admin.questions.listening') }}">Quay lại ngân hàng</a>
        </div>
    </header>

    <section class="qb-directory" aria-label="Danh sách câu hỏi đã xóa">
        @if($questions->isEmpty())
            <div class="qb-empty">
                <h2>Không có câu hỏi đã xóa</h2>
                <p>Câu hỏi xóa mềm sẽ xuất hiện ở đây để khôi phục.</p>
            </div>
        @else
            <div class="qb-table-scroll">
                <table class="qb-table">
                    <thead><tr><th>Mã câu</th><th>Nội dung</th><th>Kỳ thi</th><th>Part / Section</th><th>Đã xóa lúc</th><th>Thao tác</th></tr></thead>
                    <tbody>
                        @foreach($questions as $question)
                            <tr>
                                <td>Q-{{ str_pad((string) $question->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ \Illuminate\Support\Str::limit(strip_tags($question->prompt), 150) }}</td>
                                <td>{{ $question->examPart?->examType?->name ?? '—' }}</td>
                                <td>{{ $question->examPart?->name ?? '—' }}</td>
                                <td>{{ $question->deleted_at?->locale('vi')->diffForHumans() }}</td>
                                <td>
                                    @can('content.edit')
                                        <form method="post" action="{{ route('admin.questions.restore', $question->id) }}" data-pending-label="Đang khôi phục…">
                                            @csrf
                                            <button class="button button-quiet" type="submit">Khôi phục câu hỏi</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="qb-pagination">{{ $questions->links() }}</footer>
        @endif
    </section>
</div>
