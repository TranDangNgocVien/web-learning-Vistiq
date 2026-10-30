@php
    $typeLabels = [
        'vocabulary' => 'Từ vựng',
        'grammar' => 'Ngữ pháp',
        'collocation' => 'Collocation',
        'phrasal_verb' => 'Cụm động từ',
        'idiom' => 'Thành ngữ',
    ];
@endphp

<section class="knowledge-editor-page">
    <p>Tệp được giữ trong phiên 20 phút. Chỉ xác nhận khi toàn bộ dòng hợp lệ.</p>
    <header class="knowledge-editor-header">
        <a class="knowledge-back-link" href="{{ route('admin.knowledge.import', ['type' => $type]) }}">← Quay lại Import</a>
        <div class="knowledge-editor-title">
            <h1>Xem trước import</h1>
            <span class="knowledge-type-pill">{{ $typeLabels[$type] ?? $type }}</span>
        </div>
    </header>

    @if(count($preview['errors']) > 0)
        <div class="knowledge-validation-errors" role="alert">
            <strong>{{ count($preview['errors']) }} dòng có lỗi (chưa import; cần sửa tệp):</strong>
            <ul>
                @foreach($preview['errors'] as $err)
                    <li>Dòng {{ $err['row'] }}: {{ $err['message'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="knowledge-editor-section">
        <div class="knowledge-section-heading">
            <div>
                <h2>Tệp: {{ $filename }}</h2>
                <p>{{ $preview['total'] }} mục hợp lệ sẵn sàng import · Xử lý trùng: {{ $duplicatePolicy === 'overwrite' ? 'Ghi đè' : 'Bỏ qua' }}</p>
            </div>
        </div>

        @if($preview['total'] > 0)
            <div class="knowledge-table-scroll">
                <table class="knowledge-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>English</th>
                            <th>Nghĩa</th>
                            <th>Tags</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($preview['rows'], 0, 50) as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><strong>{{ $row['english'] ?? '—' }}</strong></td>
                                <td>{{ $row['meaning_vi'] ?? '—' }}</td>
                                <td>{{ $row['tags'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        @if($preview['total'] > 50)
                            <tr><td colspan="4" class="knowledge-empty">… và {{ $preview['total'] - 50 }} mục khác</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @else
            <p class="knowledge-empty">Không có mục hợp lệ để import.</p>
        @endif
    </section>

    @if($preview['total'] > 0 && count($preview['errors']) === 0)
        <form method="post" action="{{ route('admin.knowledge.import.confirm') }}" data-pending-label="Đang import…">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="duplicate_policy" value="{{ $duplicatePolicy }}">
            <input type="hidden" name="preview_token" value="{{ $previewToken }}">
            <footer class="knowledge-editor-actions">
                <a class="button button-quiet" href="{{ route('admin.knowledge.import', ['type' => $type]) }}">Hủy</a>
                <button class="button button-primary" type="submit">Import {{ $preview['total'] }} mục</button>
            </footer>
        </form>
    @else
        <footer class="knowledge-editor-actions">
            <a class="button button-quiet" href="{{ route('admin.knowledge.import', ['type' => $type]) }}">Quay lại</a>
        </footer>
    @endif
</section>
