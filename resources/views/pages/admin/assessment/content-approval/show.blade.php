@php($labels=['review'=>'Chờ duyệt','approved'=>$kind==='stimulus'?'Đã duyệt':'Chờ xuất bản','draft'=>'Trả chỉnh sửa / Bản nháp','published'=>'Đã xuất bản','archived'=>'Đã lưu trữ'])
@php($kindLabel=match($kind){'exam'=>'Đề thi','stimulus'=>'Bài Reading',default=>'Câu hỏi'})
<h1>Duyệt revision {{ $number }}</h1>
@if(isset($conflict))<p class="admin-alert admin-alert-error" role="alert">{{ $conflict }}</p>@endif
<section class="panel">
    <a href="{{ route('admin.content-approval.index') }}">Quay lại hàng đợi</a>
    <h2>{{ $kindLabel }} #{{ $root->id }} · Revision {{ $number }}</h2>
    <p><span class="badge badge-soft">{{ $labels[$status]??$status }}</span></p>
    @if($blockers)<div role="alert" class="admin-alert admin-alert-error"><strong>Cần hoàn thiện trước khi duyệt</strong><ul>@foreach($blockers as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('pages.admin.assessment.content-approval.payload',['content'=>$payload])
    @foreach($dependencies['stimuli']??[] as $stimulus)<section class="panel"><h3>{{ $stimulus['title']??'Tài nguyên liên kết' }}</h3>@include('pages.admin.assessment.content-approval.payload',['content'=>$stimulus])</section>@endforeach
</section>
@if($previous)
    <details class="panel"><summary>Đối chiếu revision trước</summary>
        <p>Các trường thay đổi: {{ implode(', ', array_keys(array_filter($payload, fn($value,$key)=>($previous[$key]??null)!==$value, ARRAY_FILTER_USE_BOTH))) ?: 'Không có thay đổi trong các trường hiện tại' }}</p>
        @include('pages.admin.assessment.content-approval.payload',['content'=>$previous])
    </details>
@endif
@if($status==='review')
    <section class="panel"><h2>Quyết định revision {{ $number }}</h2>
        @if((int)$author===(int)auth()->id())<p>Bạn là tác giả. Một reviewer khác cần duyệt revision này.</p>@elseif(!$blockers)
        <form method="post" action="{{ route('admin.content-approval.decide',[$kind,$root->id,'approve']) }}" data-confirm="Duyệt revision {{ $number }} của {{ $kindLabel }} #{{ $root->id }}?{{ $kind==='stimulus'?' Bài Reading sẽ chuyển sang Đã duyệt.':' Nội dung sẽ chờ xuất bản.' }}" data-confirm-label="Duyệt revision {{ $number }}">@csrf
            <input type="hidden" name="revision_counter" value="{{ $number }}"><input type="hidden" name="review_token" value="{{ $token }}">
            <button class="button button-primary">Duyệt revision {{ $number }}</button>
        </form>@endif
        <form method="post" action="{{ route('admin.content-approval.decide',[$kind,$root->id,'reject']) }}" class="admin-form-grid">@csrf
            <input type="hidden" name="revision_counter" value="{{ $number }}"><input type="hidden" name="review_token" value="{{ $token }}">
            <label>Lý do trả chỉnh sửa (bắt buộc, tối đa 2.000 ký tự)<textarea name="note" maxlength="2000" required aria-describedby="note-error">{{ old('note') }}</textarea></label>
            @error('note')<p id="note-error" role="alert">{{ $message }}</p>@enderror
            <button class="button button-quiet">Trả chỉnh sửa revision {{ $number }}</button>
        </form>
    </section>
@endif
@can('content.publish')
    @if($kind==='stimulus')<p><a href="{{ route('admin.readings.index',['stimulus'=>$root->id,'edit'=>1]) }}">Mở bài Reading trong ngân hàng</a></p>
    @else<p><a href="{{ route($kind==='exam'?'admin.exams.edit':'admin.questions.edit',$root->id) }}">Mở module nguồn để biên tập hoặc xuất bản</a></p>@endif
@endcan
<section class="panel"><h2>Lịch sử quyết định</h2>@forelse($history as $event)<details><summary>{{ $event->created_at }} · {{ $event->action }} · Actor #{{ $event->actor_id }}</summary><pre>{{ $event->before }}</pre><pre>{{ $event->after }}</pre></details>@empty<p>Chưa có quyết định.</p>@endforelse</section>
