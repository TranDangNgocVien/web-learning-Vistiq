@if(isset($content['items']))
    <p>{{ $content['title']??'' }} · {{ $content['policies']['duration_minutes']??'—' }} phút · {{ count($content['items']) }} câu</p>
    @foreach($content['sections']??[] as $section)<p>{{ $section['title']??'Part' }} · {{ count($section['item_keys']??[]) }} câu · {{ $section['duration_minutes']??0 }} phút</p>@endforeach
    @foreach($content['items'] as $item)<article class="panel"><h3>{{ $item['key']??'Câu hỏi' }} · {{ $item['type']??'' }}</h3>@include('pages.admin.assessment.content-approval.payload',['content'=>$item])</article>@endforeach
@else
    @if(isset($content['prompt']))<div>{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($content['prompt']) !!}</div>@endif
    @if(isset($content['body']))<div>{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($content['body']) !!}</div>@endif
    @foreach($content['options']??[] as $key=>$option)<p><strong>{{ $key }}</strong>: {{ is_array($option)?($option['content']??$option['text']??''):$option }}</p>@endforeach
    @if(isset($content['correct_options'])||isset($content['accepted_answers']))<p>Đáp án: {{ implode(', ', $content['correct_options']??$content['accepted_answers']??[]) }}</p>@endif
    @if(isset($content['explanation']))<div><strong>Giải thích</strong>{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($content['explanation']) !!}</div>@endif
    @foreach($content['stimuli']??[] as $stimulus)<section class="panel"><h4>{{ $stimulus['title']??'Tài nguyên' }}</h4>@include('pages.admin.assessment.content-approval.payload',['content'=>$stimulus])</section>@endforeach
    @if(($content['metadata']['asset_id']??null)&&($content['stimulus_type']??'')==='audio')<audio controls preload="none" aria-label="Nghe {{ $content['title']??'tài nguyên' }}" src="{{ route('admin.media.show',$content['metadata']['asset_id']) }}"></audio>@endif
    @if(($content['stimulus_type']??'')==='audio' && empty($content['metadata']['asset_id']) && !empty($content['asset_path']))
        @php($audioPath=$content['asset_path'])
        @php($audioUrl=preg_match('#^https?://#i',$audioPath) ? $audioPath : (str_contains($audioPath,'://') ? null : \Illuminate\Support\Facades\Storage::disk('public')->url($audioPath)))
        @if($audioUrl)<audio controls preload="none" aria-label="Nghe {{ $content['title']??'tài nguyên' }}" src="{{ $audioUrl }}"></audio>@else<p role="alert">Đường dẫn audio không được hỗ trợ. Cập nhật tài nguyên tại module nguồn.</p>@endif
    @endif
    @if($content['metadata']['audio_id']??null)<audio controls preload="none" aria-label="Audio câu hỏi" src="{{ route('admin.media.show',$content['metadata']['audio_id']) }}"></audio>@endif
    @if($content['metadata']['image_id']??null)<img alt="Hình minh họa câu hỏi" src="{{ route('admin.media.show',$content['metadata']['image_id']) }}">@endif
    @if(isset($content['transcript']))<details><summary>Transcript</summary><p>{{ $content['transcript'] }}</p></details>@endif
    @if(!empty($content['metadata']))<details><summary>Rubric và cấu hình câu hỏi</summary><pre>{{ json_encode($content['metadata'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
@endif
