@php($typeLabels=['vocabulary'=>'Từ vựng','grammar'=>'Ngữ pháp','collocation'=>'Collocation','phrasal_verb'=>'Cụm động từ','idiom'=>'Thành ngữ'])
<p><a href="{{ route('admin.knowledge.index') }}">Quay lại kho kiến thức</a></p>
@include('shared.workflow-actions',['kind'=>'knowledge','entityId'=>$item->id,'revision'=>$workflowRevision])
@can('content.edit')<p><a class="button button-quiet" href="{{ route('admin.knowledge.edit',$item) }}">Chỉnh sửa bản nháp</a></p>@endcan
@include('pages.admin.learning.knowledge.workspace.preview')
