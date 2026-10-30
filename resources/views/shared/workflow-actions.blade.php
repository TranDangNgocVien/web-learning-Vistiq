@php($revisionStatus = $revision?->status ?? 'draft')
<div class="assessment-actions"><span class="badge badge-soft">{{ strtoupper($revisionStatus) }}</span>
@foreach(['review'=>['content.edit','draft','Gửi duyệt'],'approve'=>['content.review','review','Duyệt'],'reject'=>['content.review','review','Từ chối'],'publish'=>['content.publish','approved','Xuất bản'],'archive'=>['content.publish','published','Archive']] as $action=>$definition)
@can($definition[0]) @if($revisionStatus===$definition[1])
@if($kind==='question' && in_array($action,['approve','reject']))
@if($action==='approve')<a class="button button-quiet" href="{{ route('admin.content-approval.show',['question',$entityId]) }}">Mở duyệt revision</a>@endif
@else
<form method="post" action="{{ route('admin.content.transition',['kind'=>$kind,'id'=>$entityId,'action'=>$action]) }}">@csrf
@if($action==='reject')<input name="note" required placeholder="Lý do từ chối" aria-label="Lý do từ chối">@endif
<button class="button button-quiet">{{ $definition[2] }}</button></form>
@endif
@endif @endcan @endforeach
@can('content.edit')<form method="post" action="{{ route('admin.content.clone',['kind'=>$kind,'id'=>$entityId]) }}">@csrf<button class="button button-quiet">Tạo draft mới</button></form>@endcan
</div>
