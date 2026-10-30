<aside class="panel" id="knowledge-preview" tabindex="-1">
    <h2>Xem trước bản nháp</h2><p>Lưu thay đổi để cập nhật bản xem trước. Bản nháp chưa hiển thị với học viên.</p>
    <span class="badge badge-soft">{{ $typeLabels[$selectedType] ?? $selectedType }}</span>
    <h3>{{ $payload['english'] ?? 'Chưa có nội dung' }}</h3>
    @if($payload['ipa'] ?? null)<p>{{ $payload['ipa'] }}</p>@endif
    @if($payload['meaning_vi'] ?? null)<p>{{ $payload['meaning_vi'] }}</p>@endif
    @if($payload['summary'] ?? null)<p>{{ $payload['summary'] }}</p>@endif
    <div>{!! app(\VistiqCore\Services\Knowledge\KnowledgeTaxonomyService::class)->sanitizeHtml($payload['content'] ?? '') !!}</div>
    @foreach($payload['grammar_rules'] ?? [] as $rule)
        <h3>{{ $rule['title'] }}</h3><p>{{ $rule['formula'] ?? '' }}</p><div>{!! app(\VistiqCore\Services\Knowledge\KnowledgeTaxonomyService::class)->sanitizeHtml($rule['explanation'] ?? '') !!}</div>
    @endforeach
    @foreach($payload['examples'] ?? [] as $example)<p><b>{{ $example['english'] }}</b><br>{{ $example['vietnamese'] ?? '' }}</p>@endforeach
</aside>
