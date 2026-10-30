<form class="dictionary-search-form" method="get" action="{{ route('lookup.index') }}" data-dictionary-search data-suggestions-url="{{ route('api.dictionary.suggestions') }}" data-ajax-filter-form data-ajax-target="#dictionary-search-results" data-ajax-debounce="350">
    <label class="dictionary-sr-only" for="dictionary-query">Từ, cụm từ hoặc nghĩa tiếng Việt</label>
    <div class="dictionary-search-controls">
        <div class="dictionary-input-wrap">
            <span class="dictionary-search-icon">@include('pages.dictionary.icon', ['name' => 'search'])</span>
            <input id="dictionary-query" name="q" value="{{ is_string(old('q', $term)) ? old('q', $term) : $term }}" placeholder="Nhập từ, cụm từ hoặc nghĩa tiếng Việt…" maxlength="100" autocomplete="off" aria-describedby="dictionary-search-help{{ $errors->has('q') ? ' dictionary-query-error' : '' }}" aria-invalid="{{ $errors->has('q') ? 'true' : 'false' }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="dictionary-suggestions" data-dictionary-input>
            <button class="dictionary-clear" type="button" aria-label="Xóa tìm kiếm" data-dictionary-clear hidden>@include('pages.dictionary.icon', ['name' => 'close'])</button>
            <ul id="dictionary-suggestions" class="dictionary-suggestions" role="listbox" aria-label="Gợi ý tra cứu" hidden data-dictionary-suggestions></ul>
        </div>
        <select class="dictionary-sr-only" name="type" aria-label="Loại nội dung" tabindex="-1">
            @foreach (['all' => 'Tất cả', 'vocabulary' => 'Từ vựng', 'collocation' => 'Collocation', 'phrasal_verb' => 'Phrasal Verb', 'grammar' => 'Ngữ pháp', 'idiom' => 'Thành ngữ'] as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="dictionary-submit" type="submit">Tra cứu</button>
    </div>
    <nav class="dictionary-types" aria-label="Loại nội dung tra cứu">
        @foreach (['all' => 'Tất cả', 'vocabulary' => 'Từ vựng', 'collocation' => 'Collocation', 'phrasal_verb' => 'Phrasal Verb', 'grammar' => 'Ngữ pháp', 'idiom' => 'Thành ngữ'] as $value => $label)
            <button type="submit" name="type" value="{{ $value }}" class="{{ $type === $value && $activeTab !== 'examples' ? 'is-active' : '' }}" @if ($type === $value && $activeTab !== 'examples') aria-current="true" @endif>{{ $label }}</button>
        @endforeach
        <button type="submit" name="tab" value="examples" class="{{ $activeTab === 'examples' ? 'is-active' : '' }}" @if ($activeTab === 'examples') aria-current="true" @endif>Ví dụ</button>
    </nav>
    <p id="dictionary-search-help" class="dictionary-sr-only">Tra cứu miễn phí, không cần đăng nhập. Nhấn Enter để tìm kiếm.</p>
    @error('q')<p id="dictionary-query-error" class="dictionary-error" role="alert">{{ $message }}</p>@enderror
    @foreach (['type', 'tab', 'page', 'per_page'] as $field)
        @error($field)<p class="dictionary-error" role="alert">{{ $message }}</p>@enderror
    @endforeach
    <p class="dictionary-help" role="status" aria-live="polite" data-dictionary-status></p>
</form>
