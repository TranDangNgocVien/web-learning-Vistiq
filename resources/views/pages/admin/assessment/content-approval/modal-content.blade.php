@php($labels=['review'=>'Chờ duyệt','approved'=>$kind==='stimulus'?'Đã duyệt':'Chờ xuất bản','draft'=>'Bản nháp / Trả chỉnh sửa','published'=>'Đã xuất bản','archived'=>'Đã lưu trữ'])
@php($kindLabel=match($kind){'exam'=>'Đề thi','stimulus'=>'Bài Reading',default=>'Câu hỏi'})
<article class="content-review-item" data-review-item="{{ $kind }}-{{ $root->id }}">
    <header class="content-review-item-heading">
        <div>
            <p class="content-review-kicker">{{ $kindLabel }} · #{{ $root->id }}</p>
            <h3>{{ in_array($kind,['exam','stimulus'],true) ? ($payload['title']??$root->title??$kindLabel) : \Illuminate\Support\Str::limit(strip_tags($payload['prompt']??$payload['body']??'Câu hỏi'), 160) }}</h3>
            <p>Revision {{ $number }} · <span class="badge badge-soft">{{ $labels[$status]??$status }}</span></p>
        </div>
    </header>

    @if($blockers)
        <div class="admin-alert admin-alert-error" role="alert">
            <strong>Chưa thể duyệt revision này</strong>
            <ul>@foreach($blockers as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="content-review-question" aria-label="Nội dung revision">
        <h4>Thông tin nội dung</h4>
        @include('pages.admin.assessment.content-approval.payload',['content'=>$payload])
        @foreach($dependencies['stimuli']??[] as $stimulus)
            <section class="content-review-stimulus">
                <h4>{{ $stimulus['title']??'Tài nguyên liên kết' }}</h4>
                @include('pages.admin.assessment.content-approval.payload',['content'=>$stimulus])
            </section>
        @endforeach
    </section>

    @if($previous)
        <details class="content-review-previous">
            <summary>Đối chiếu revision trước</summary>
            <p>Các trường thay đổi: {{ implode(', ', array_keys(array_filter($payload, fn($value,$key)=>($previous[$key]??null)!==$value, ARRAY_FILTER_USE_BOTH))) ?: 'Không có thay đổi trong các trường hiện tại' }}</p>
            @include('pages.admin.assessment.content-approval.payload',['content'=>$previous])
        </details>
    @endif

    @if($status==='review')
        <div class="content-review-form-error" data-review-form-error role="alert" hidden></div>
        <div class="content-review-decision">
            @php($isOwnRevision = (int) $author === (int) auth()->id())
            @php($canSuperAdminSelfReview = $isOwnRevision && auth()->user()->hasRole('super-admin'))
            @if($isOwnRevision && !$canSuperAdminSelfReview)
                <p role="status">Bạn là tác giả của revision này. Một reviewer khác cần đưa ra quyết định.</p>
            @elseif($canSuperAdminSelfReview)
                <p class="admin-alert" role="status">Super Admin có thể duyệt mọi revision, kể cả nội dung do chính mình tạo. Lần duyệt này sẽ được ghi vào nhật ký.</p>
            @endif
            @if(!$blockers && (!$isOwnRevision || $canSuperAdminSelfReview))
                @php($approvalConfirmation = $kind==='stimulus'
                    ? 'Duyệt bài Reading #'.$root->id.', revision '.$number.'? Bài sẽ chuyển sang Đã duyệt.'
                    : ($isOwnRevision
                        ? 'Super Admin duyệt '.$kind.' #'.$root->id.', revision '.$number.' do chính mình tạo? Thao tác sẽ được ghi vào nhật ký và chuyển nội dung sang Chờ xuất bản; người học chưa thấy nội dung cho đến khi phát hành.'
                        : 'Duyệt '.$kind.' #'.$root->id.', revision '.$number.'? Nội dung sẽ chuyển sang Chờ xuất bản; người học chưa thấy nội dung cho đến khi phát hành.'))
                <form method="post" action="{{ route('admin.content-approval.decide',[$kind,$root->id,'approve']) }}" data-content-review-decision data-review-action="approve" data-review-confirm-message="{{ $approvalConfirmation }}">
                    @csrf
                    <input type="hidden" name="revision_counter" value="{{ $number }}">
                    <input type="hidden" name="review_token" value="{{ $token }}">
                    <button type="submit" class="button button-primary" data-pending-label="Đang duyệt…">Xác nhận duyệt</button>
                </form>
            @endif
            <details class="content-review-reject">
                <summary class="button button-danger">Từ chối</summary>
                <form method="post" action="{{ route('admin.content-approval.decide',[$kind,$root->id,'reject']) }}" class="content-review-reject-form" data-content-review-decision data-review-action="reject">
                    @csrf
                    <input type="hidden" name="revision_counter" value="{{ $number }}">
                    <input type="hidden" name="review_token" value="{{ $token }}">
                    <label for="review-rejection-note">Lý do từ chối / trả chỉnh sửa <span aria-hidden="true">*</span>
                        <textarea id="review-rejection-note" name="note" maxlength="2000" required aria-describedby="review-rejection-hint review-rejection-error"></textarea>
                    </label>
                    <small id="review-rejection-hint">Tối đa 2.000 ký tự. Nội dung sẽ quay về bản nháp để biên tập viên chỉnh sửa.</small>
                    <p id="review-rejection-error" class="form-field-error" data-review-field-error="note" role="alert"></p>
                    <button type="submit" class="button button-danger" data-pending-label="Đang trả chỉnh sửa…">Xác nhận từ chối và trả chỉnh sửa</button>
                </form>
            </details>
        </div>
    @else
        <p class="admin-alert" role="status">Revision này đã được xử lý nên không còn nút ra quyết định.</p>
    @endif
</article>
