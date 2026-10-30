<div class="back-link"><a href="{{ route('knowledge.index') }}">← Kho kiến thức</a></div>
<div class="knowledge-detail-grid">
    <article class="panel learning-article"><span class="badge badge-blue">{{ $summary->type_label }}{{ $summary->level_label ? ' · '.$summary->level_label : '' }}</span><h1>{{ $summary->canonical_text }}</h1>
        @if($payload['ipa'] ?? null)<p>{{ $payload['ipa'] }}</p>@endif
        @if($payload['meaning_vi'] ?? null)<h2>Ý nghĩa</h2><p>{{ $payload['meaning_vi'] }}</p>@endif
        @if($payload['definition_en'] ?? null)<p>{{ $payload['definition_en'] }}</p>@endif
        @if($payload['summary'] ?? null)<p>{{ $payload['summary'] }}</p>@endif
        @if($payload['content'] ?? null)<div>{!! $payload['content'] !!}</div>@endif
        @foreach($payload['grammar_rules'] ?? [] as $rule)<section><h2>{{ $rule['title'] }}</h2>@if($rule['formula'] ?? null)<p>{{ $rule['formula'] }}</p>@endif<div>{!! $rule['explanation'] ?? '' !!}</div></section>@endforeach
        @if($payload['examples'] ?? [])<h2>Ví dụ</h2>@foreach($payload['examples'] as $example)<div class="definition-example"><b>{{ $example['english'] }}</b><p>{{ $example['vietnamese'] ?? '' }}</p></div>@endforeach
        @endif
        @if($payload['usage_note'] ?? null)<p>{{ $payload['usage_note'] }}</p>@endif
    </article>
    <aside class="panel personal-note"><h2>Ghi chú cá nhân</h2>
        @auth
        <form method="post" action="{{ route('knowledge.save', $summary->slug) }}" data-pending-label="Đang lưu…">@csrf
            <label for="knowledge-note">Ghi chú (tối đa 5.000 ký tự)<textarea id="knowledge-note" name="personal_note" rows="6" maxlength="5000" @error('personal_note') aria-invalid="true" aria-describedby="knowledge-note-error" @enderror>{{ old('personal_note', $saved?->personal_note) }}</textarea></label>
            @error('personal_note')<p id="knowledge-note-error" role="alert">{{ $message }}</p>@enderror
            <input type="hidden" name="is_starred" value="0"><label><input type="checkbox" name="is_starred" value="1" @checked(old('is_starred', $saved?->is_starred))> Đánh dấu quan trọng</label>
            <button class="button button-primary" type="submit">{{ $saved ? 'Lưu ghi chú' : 'Lưu kiến thức' }}</button>
        </form>
        @if($saved)<form method="post" action="{{ route('knowledge.unsave', $summary->slug) }}" data-confirm="Bỏ lưu {{ $summary->canonical_text }}? Ghi chú cá nhân của mục này cũng sẽ bị xóa." data-confirm-label="Bỏ lưu kiến thức">@csrf @method('DELETE')<button class="button button-quiet" type="submit">Bỏ lưu kiến thức</button></form>@endif
        @else<p>Đăng nhập để lưu kiến thức và ghi chú riêng.</p><a class="button button-primary" href="{{ route('login') }}">Đăng nhập</a>@endauth
    </aside>
</div>
